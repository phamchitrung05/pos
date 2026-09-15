<?php

namespace App\Actions\Pos;

use App\Enums\OrderStatus;
use App\Enums\PrinterType;
use App\Enums\PrintJobStatus;
use App\Enums\PrintType;
use App\Enums\TableSessionStatus;
use App\Events\PosStateChanged;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\User;
use App\Services\Pos\PosActivityLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Tạo snapshot phiếu bếp chỉ gồm số lượng món chưa từng được gửi đi. */
final class CreateKitchenPrintJob
{
    public function __construct(private readonly PosActivityLogger $activityLogger) {}

    /**
     * Việc tạo payload và cập nhật `kitchen_printed_quantity` nằm trong cùng
     * transaction. Nếu một bước thất bại, cả hai cùng rollback nên hệ thống
     * không thể đánh dấu món đã in khi chưa có PrintJob để thiết bị xử lý.
     */
    public function handle(Order $order, Printer $printer, User $actor): PrintJob
    {
        $printJob = DB::transaction(function () use ($order, $printer, $actor): PrintJob {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->with(['store', 'tableSession.table'])
                ->lockForUpdate()
                ->findOrFail($order->getKey());

            /** @var Printer $lockedPrinter */
            $lockedPrinter = Printer::query()
                ->lockForUpdate()
                ->findOrFail($printer->getKey());

            Gate::forUser($actor)->authorize('view', $lockedOrder);
            Gate::forUser($actor)->authorize('view', $lockedPrinter);
            Gate::forUser($actor)->authorize('create', PrintJob::class);

            if ($lockedOrder->status !== OrderStatus::Open || $lockedOrder->tableSession?->status !== TableSessionStatus::Open) {
                throw ValidationException::withMessages([
                    'order_id' => 'Chỉ order đang phục vụ mới có thể tạo phiếu bếp.',
                ]);
            }

            if ((int) $lockedPrinter->store_id !== (int) $lockedOrder->store_id) {
                throw ValidationException::withMessages([
                    'printer_id' => 'Máy in bếp không thuộc cùng cửa hàng với order.',
                ]);
            }

            if (! $lockedPrinter->is_active
                || blank($lockedPrinter->ip_address)
                || ! $lockedPrinter->port
                || ! in_array($lockedPrinter->printer_type, [PrinterType::Kitchen, PrinterType::Both], true)) {
                throw ValidationException::withMessages([
                    'printer_id' => 'Máy in được chọn không phải máy in bếp đang hoạt động.',
                ]);
            }

            /** @var Collection<int, OrderItem> $items */
            $items = OrderItem::query()
                ->where('order_id', $lockedOrder->getKey())
                ->whereColumn('quantity', '>', 'kitchen_printed_quantity')
                ->with('product')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Order không có món mới cần in cho bếp.',
                ]);
            }

            // Payload là snapshot độc lập để lần in lại không bị ảnh hưởng khi order thay đổi.
            $payload = [
                'version' => 1,
                'type' => PrintType::Kitchen->value,
                'store' => [
                    'id' => (int) $lockedOrder->store_id,
                    'name' => $lockedOrder->store?->name,
                ],
                'printer' => [
                    'id' => (int) $lockedPrinter->getKey(),
                    'name' => $lockedPrinter->name,
                ],
                'document' => [
                    'paper_width_mm' => $lockedPrinter->paper_width_mm->value,
                    'dots_per_line' => $lockedPrinter->paper_width_mm->dotsPerLine(),
                    'locale' => 'vi-VN',
                    'render_mode' => 'raw',
                ],
                'order' => [
                    'id' => (int) $lockedOrder->getKey(),
                    'code' => $lockedOrder->code,
                    'table' => $lockedOrder->tableSession?->table?->name,
                    'created_at' => $lockedOrder->created_at?->toIso8601String(),
                ],
                'items' => $items->map(fn (OrderItem $item): array => [
                    'order_item_id' => (int) $item->getKey(),
                    'product_name' => $item->product?->name ?? 'Sản phẩm đã xóa',
                    'quantity' => $item->quantity - $item->kitchen_printed_quantity,
                    'notes' => $item->notes,
                ])->values()->all(),
                'requested_at' => now()->toIso8601String(),
                'requested_by' => [
                    'id' => (int) $actor->getKey(),
                    'name' => $actor->name,
                ],
            ];

            $printJob = new PrintJob;
            $printJob->forceFill([
                'store_id' => $lockedOrder->store_id,
                'printer_id' => $lockedPrinter->getKey(),
                'order_id' => $lockedOrder->getKey(),
                'print_type' => PrintType::Kitchen,
                'status' => PrintJobStatus::Pending,
                'attempts' => 0,
                'payload' => $payload,
            ]);
            $printJob->save();

            foreach ($items as $item) {
                // Dùng update trên model đang khóa để mốc in khớp chính xác với snapshot vừa tạo.
                $item->forceFill([
                    'kitchen_printed_quantity' => $item->quantity,
                ])->save();
            }

            $this->activityLogger->kitchenTicketCreated($printJob, $actor);

            return $printJob->refresh()->load(['printer', 'order.tableSession']);
        });

        PosStateChanged::dispatch((int) $printJob->store_id, (int) $printJob->order->tableSession->table_id, 'kitchen-ticket.created');

        return $printJob;
    }
}
