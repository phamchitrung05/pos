<?php

namespace App\Actions\Pos;

use App\Enums\PaymentStatus;
use App\Enums\PrinterType;
use App\Enums\PrintJobStatus;
use App\Enums\PrintType;
use App\Models\Payment;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Tạo snapshot hóa đơn từ payment hoàn tất, idempotent theo payment. */
final class CreateReceiptPrintJob
{
    public function handle(Payment $payment, Printer $printer, User $actor): PrintJob
    {
        return DB::transaction(function () use ($payment, $printer, $actor): PrintJob {
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()
                ->with(['store', 'receivedBy', 'order.tableSession.table', 'order.items.product'])
                ->lockForUpdate()
                ->findOrFail($payment->getKey());
            /** @var Printer $lockedPrinter */
            $lockedPrinter = Printer::query()->lockForUpdate()->findOrFail($printer->getKey());

            Gate::forUser($actor)->authorize('view', $lockedPayment);
            Gate::forUser($actor)->authorize('view', $lockedPrinter);
            Gate::forUser($actor)->authorize('create', PrintJob::class);

            $existingJob = PrintJob::query()
                ->where('payment_id', $lockedPayment->getKey())
                ->where('print_type', PrintType::Receipt->value)
                ->first();

            if ($existingJob) {
                return $existingJob->load(['printer', 'order', 'payment']);
            }

            if ($lockedPayment->status !== PaymentStatus::Completed) {
                throw ValidationException::withMessages(['payment_id' => 'Chỉ payment hoàn tất mới được in hóa đơn.']);
            }

            if ((int) $lockedPrinter->store_id !== (int) $lockedPayment->store_id
                || ! $lockedPrinter->is_active
                || blank($lockedPrinter->ip_address)
                || ! $lockedPrinter->port
                || $lockedPrinter->printer_type !== PrinterType::Receipt) {
                throw ValidationException::withMessages(['printer_id' => 'Máy in hóa đơn không hợp lệ cho cửa hàng này.']);
            }

            $order = $lockedPayment->order;
            $job = new PrintJob;
            $job->forceFill([
                'store_id' => $lockedPayment->store_id,
                'printer_id' => $lockedPrinter->getKey(),
                'order_id' => $order->getKey(),
                'payment_id' => $lockedPayment->getKey(),
                'print_type' => PrintType::Receipt,
                'status' => PrintJobStatus::Pending,
                'attempts' => 0,
                // Payload là chứng từ bất biến; retry không đọc lại tên hay giá sản phẩm hiện tại.
                'payload' => [
                    'version' => 1,
                    'type' => PrintType::Receipt->value,
                    'store' => [
                        'id' => (int) $lockedPayment->store_id,
                        'name' => $lockedPayment->store?->name,
                        'address' => $lockedPayment->store?->address,
                        'phone' => $lockedPayment->store?->phone,
                    ],
                    'document' => [
                        'paper_width_mm' => $lockedPrinter->paper_width_mm->value,
                        'dots_per_line' => $lockedPrinter->paper_width_mm->dotsPerLine(),
                        'locale' => 'vi-VN',
                        'render_mode' => 'raster',
                    ],
                    'order' => [
                        'id' => (int) $order->getKey(),
                        'code' => $order->code,
                        'table' => $order->tableSession?->table?->name,
                        'items' => $order->items->map(fn ($item): array => [
                            'name' => $item->product?->name ?? 'Sản phẩm đã xóa',
                            'quantity' => $item->quantity,
                            // Tiền VND dùng số nguyên để JSON không tạo sai số dấu phẩy động ở agent.
                            'unit_price' => (int) round((float) $item->unit_price),
                            'subtotal' => (int) round((float) $item->unit_price * $item->quantity),
                            'notes' => $item->notes,
                        ])->values()->all(),
                        'total' => (int) round((float) $order->total),
                    ],
                    'payment' => [
                        'id' => (int) $lockedPayment->getKey(),
                        'method' => $lockedPayment->payment_method->value,
                        'amount' => (int) round((float) $lockedPayment->amount),
                        'paid_at' => $lockedPayment->paid_at?->toIso8601String(),
                        'received_by' => $lockedPayment->receivedBy?->name,
                    ],
                ],
            ]);
            $job->save();

            return $job->refresh()->load(['printer', 'order', 'payment']);
        });
    }
}
