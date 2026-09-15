<?php

namespace App\Actions\Pos;

use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Events\PosStateChanged;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Pos\PosActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Cập nhật số lượng, ghi chú hoặc giá snapshot của một dòng món. */
final class UpdateOrderItem
{
    public function __construct(
        private readonly RecalculateOrderTotal $recalculateOrderTotal,
        private readonly PosActivityLogger $activityLogger,
    ) {}

    /**
     * Món đã gửi bếp vẫn được giảm số lượng vì khách có thể phản hồi món bị nhập
     * sai. Khi số lượng về 0, dòng món bị xóa khỏi order hiện tại. Ghi chú vẫn
     * khóa sau khi in để nội dung chế biến không bị đổi âm thầm.
     */
    public function handle(
        OrderItem $orderItem,
        User $actor,
        int $quantity,
        ?string $notes = null,
        ?int $unitPrice = null,
    ): OrderItem {
        $validated = Validator::make(
            ['quantity' => $quantity, 'notes' => $notes, 'unit_price' => $unitPrice],
            [
                'quantity' => ['required', 'integer', 'min:0', 'max:999'],
                'notes' => ['nullable', 'string', 'max:1000'],
                'unit_price' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            ],
            attributes: ['quantity' => 'số lượng', 'notes' => 'ghi chú món', 'unit_price' => 'đơn giá món'],
        )->validate();

        $updatedItem = DB::transaction(function () use ($orderItem, $actor, $validated): OrderItem {
            // Chỉ dùng ID model đầu vào; order_id phải đọc lại từ bản ghi authoritative trong DB.
            $authoritativeOrderId = OrderItem::query()
                ->whereKey($orderItem->getKey())
                ->value('order_id');

            // Khóa order trước item để mọi action cùng một thứ tự khóa, hạn chế deadlock.
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->with('tableSession')
                ->lockForUpdate()
                ->findOrFail($authoritativeOrderId);

            /** @var OrderItem $lockedItem */
            $lockedItem = OrderItem::query()
                ->lockForUpdate()
                ->findOrFail($orderItem->getKey());

            if ((int) $lockedItem->order_id !== (int) $lockedOrder->getKey()) {
                throw ValidationException::withMessages([
                    'order_id' => 'Dòng món vừa thay đổi order, vui lòng tải lại trước khi thao tác.',
                ]);
            }

            Gate::forUser($actor)->authorize('update', $lockedItem);

            if ($lockedOrder->status !== OrderStatus::Open || $lockedOrder->tableSession?->status !== TableSessionStatus::Open) {
                throw ValidationException::withMessages([
                    'order_id' => 'Không thể sửa món của order đã thanh toán hoặc đã hủy.',
                ]);
            }

            $normalizedNotes = filled($validated['notes'] ?? null)
                ? trim((string) $validated['notes'])
                : null;

            if ((int) $validated['quantity'] === 0) {
                $this->activityLogger->itemDeleted($lockedItem, $actor);
                $lockedItem->delete();
                $updatedOrder = $this->recalculateOrderTotal->handle($lockedOrder);

                if (! $updatedOrder->items()->exists()) {
                    /** @var TableSession $lockedSession */
                    $lockedSession = TableSession::query()
                        ->lockForUpdate()
                        ->findOrFail($updatedOrder->table_session_id);
                    $updatedOrder->forceFill(['status' => OrderStatus::Cancelled])->save();
                    $lockedSession->forceFill([
                        'status' => TableSessionStatus::Cancelled,
                        'end_time' => now(),
                        'closed_by' => $actor->getKey(),
                    ])->save();
                    $this->activityLogger->sessionClosed($lockedSession, $actor);
                    $updatedOrder->setRelation('tableSession', $lockedSession);
                } else {
                    $updatedOrder->load('tableSession');
                }

                $lockedItem->setRelation('order', $updatedOrder);

                return $lockedItem;
            }

            if ($lockedItem->kitchen_printed_quantity > 0 && $normalizedNotes !== $lockedItem->notes) {
                throw ValidationException::withMessages([
                    'notes' => 'Không thể đổi ghi chú sau khi món đã được gửi xuống bếp.',
                ]);
            }

            $changes = [
                'quantity' => (int) $validated['quantity'],
                'notes' => $normalizedNotes,
            ];
            if ($validated['unit_price'] !== null) {
                $changes['unit_price'] = (int) $validated['unit_price'];
            }
            $oldValues = [
                'quantity' => (int) $lockedItem->quantity,
                'notes' => $lockedItem->notes,
                'unit_price' => (float) $lockedItem->unit_price,
            ];
            $lockedItem->forceFill($changes)->save();
            $newValues = [
                'quantity' => (int) $lockedItem->quantity,
                'notes' => $lockedItem->notes,
                'unit_price' => (float) $lockedItem->unit_price,
            ];

            if ($oldValues !== $newValues) {
                $this->activityLogger->itemUpdated($lockedItem, $actor, $oldValues, $newValues);
            }

            $this->recalculateOrderTotal->handle($lockedOrder);

            return $lockedItem->refresh()->load(['order.tableSession', 'product']);
        });

        PosStateChanged::dispatch(
            (int) $updatedItem->store_id,
            (int) $updatedItem->order->tableSession->table_id,
            $updatedItem->order->status === OrderStatus::Cancelled ? 'session.cancelled' : 'order.item-updated',
        );

        return $updatedItem;
    }
}
