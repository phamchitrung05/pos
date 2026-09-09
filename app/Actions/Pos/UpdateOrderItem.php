<?php

namespace App\Actions\Pos;

use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Cập nhật số lượng hoặc ghi chú của một dòng món nhưng không cho sửa giá snapshot. */
final class UpdateOrderItem
{
    public function __construct(private readonly RecalculateOrderTotal $recalculateOrderTotal) {}

    /**
     * Món đã gửi bếp không được giảm số lượng hoặc đổi ghi chú vì bếp đã nhận
     * thông tin cũ. Nghiệp vụ hủy món sau khi in sẽ cần một action và phiếu hủy
     * riêng ở giai đoạn sau để lịch sử luôn truy vết được.
     */
    public function handle(OrderItem $orderItem, User $actor, int $quantity, ?string $notes = null): OrderItem
    {
        $validated = Validator::make(
            ['quantity' => $quantity, 'notes' => $notes],
            [
                'quantity' => ['required', 'integer', 'min:1', 'max:999'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ],
            attributes: ['quantity' => 'số lượng', 'notes' => 'ghi chú món'],
        )->validate();

        return DB::transaction(function () use ($orderItem, $actor, $validated): OrderItem {
            // Khóa order trước item để mọi action cùng một thứ tự khóa, hạn chế deadlock.
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->with('tableSession')
                ->lockForUpdate()
                ->findOrFail($orderItem->order_id);

            /** @var OrderItem $lockedItem */
            $lockedItem = OrderItem::query()
                ->lockForUpdate()
                ->findOrFail($orderItem->getKey());

            Gate::forUser($actor)->authorize('update', $lockedItem);

            if ($lockedOrder->status !== OrderStatus::Open || $lockedOrder->tableSession?->status !== TableSessionStatus::Open) {
                throw ValidationException::withMessages([
                    'order_id' => 'Không thể sửa món của order đã thanh toán hoặc đã hủy.',
                ]);
            }

            $normalizedNotes = filled($validated['notes'] ?? null)
                ? trim((string) $validated['notes'])
                : null;

            if ((int) $validated['quantity'] < $lockedItem->kitchen_printed_quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Số lượng không được nhỏ hơn phần đã in cho bếp.',
                ]);
            }

            if ($lockedItem->kitchen_printed_quantity > 0 && $normalizedNotes !== $lockedItem->notes) {
                throw ValidationException::withMessages([
                    'notes' => 'Không thể đổi ghi chú sau khi món đã được gửi xuống bếp.',
                ]);
            }

            $lockedItem->forceFill([
                'quantity' => (int) $validated['quantity'],
                'notes' => $normalizedNotes,
            ])->save();

            $this->recalculateOrderTotal->handle($lockedOrder);

            return $lockedItem->refresh()->load(['order', 'product']);
        });
    }
}
