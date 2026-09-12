<?php

namespace App\Actions\Pos;

use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Events\PosStateChanged;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Thêm một hoặc nhiều món vào order đang phục vụ và tính lại tổng tiền. */
final class AddOrderItems
{
    public function __construct(private readonly RecalculateOrderTotal $recalculateOrderTotal) {}

    /**
     * @param  array<int, array{product_id: int, quantity: int, notes?: string|null, unit_price?: int}>  $items
     *
     * Nếu nhân viên không đổi giá, action chụp giá Product tại server. Giá tùy
     * chỉnh vẫn đi qua command journal và policy nên có thể truy vết người thao tác.
     */
    public function handle(Order $order, User $actor, array $items): Order
    {
        $validatedItems = Validator::make(
            ['items' => $items],
            [
                'items' => ['required', 'array', 'min:1'],
                'items.*.product_id' => ['required', 'integer'],
                'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
                'items.*.notes' => ['nullable', 'string', 'max:1000'],
                'items.*.unit_price' => ['sometimes', 'integer', 'min:0', 'max:999999999'],
            ],
            attributes: [
                'items' => 'danh sách món',
                'items.*.product_id' => 'sản phẩm',
                'items.*.quantity' => 'số lượng',
                'items.*.notes' => 'ghi chú món',
                'items.*.unit_price' => 'đơn giá món',
            ],
        )->validate()['items'];

        $updatedOrder = DB::transaction(function () use ($order, $actor, $validatedItems): Order {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->with('tableSession')
                ->lockForUpdate()
                ->findOrFail($order->getKey());

            Gate::forUser($actor)->authorize('update', $lockedOrder);
            Gate::forUser($actor)->authorize('create', OrderItem::class);

            if ($lockedOrder->status !== OrderStatus::Open || $lockedOrder->tableSession?->status !== TableSessionStatus::Open) {
                throw ValidationException::withMessages([
                    'order_id' => 'Chỉ có thể thêm món vào order của một phiên bàn đang mở.',
                ]);
            }

            /** @var Collection<int, Product> $products */
            $products = Product::query()
                ->whereIn('id', collect($validatedItems)->pluck('product_id')->unique())
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Product $product): int => (int) $product->getKey());

            foreach ($validatedItems as $index => $itemData) {
                /** @var Product|null $product */
                $product = $products->get((int) $itemData['product_id']);

                if (! $product || (int) $product->store_id !== (int) $lockedOrder->store_id || ! $product->is_active) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => 'Sản phẩm không tồn tại, đã ngừng bán hoặc không thuộc cửa hàng của order.',
                    ]);
                }

                $notes = filled($itemData['notes'] ?? null)
                    ? trim((string) $itemData['notes'])
                    : null;

                // Cùng sản phẩm và ghi chú được gộp để giao diện và phiếu bếp không có dòng trùng.
                $existingItemQuery = OrderItem::query()
                    ->where('order_id', $lockedOrder->getKey())
                    ->where('product_id', $product->getKey());

                $notes === null
                    ? $existingItemQuery->whereNull('notes')
                    : $existingItemQuery->where('notes', $notes);

                /** @var OrderItem|null $existingItem */
                $existingItem = $existingItemQuery->lockForUpdate()->first();

                if ($existingItem) {
                    Gate::forUser($actor)->authorize('update', $existingItem);

                    $newQuantity = $existingItem->quantity + (int) $itemData['quantity'];

                    if ($newQuantity > 999) {
                        throw ValidationException::withMessages([
                            "items.{$index}.quantity" => 'Tổng số lượng của một dòng món không được vượt quá 999.',
                        ]);
                    }

                    $changes = ['quantity' => $newQuantity];
                    if (array_key_exists('unit_price', $itemData)) {
                        // Popup giá áp dụng cho cả dòng gộp, gồm số lượng cũ và phần vừa thêm.
                        $changes['unit_price'] = (int) $itemData['unit_price'];
                    }
                    $existingItem->forceFill($changes)->save();

                    continue;
                }

                $newItem = new OrderItem;
                $newItem->forceFill([
                    'store_id' => $lockedOrder->store_id,
                    'order_id' => $lockedOrder->getKey(),
                    'product_id' => $product->getKey(),
                    'quantity' => (int) $itemData['quantity'],
                    'kitchen_printed_quantity' => 0,
                    'unit_price' => array_key_exists('unit_price', $itemData)
                        ? (int) $itemData['unit_price']
                        : $product->price,
                    'notes' => $notes,
                ]);
                $newItem->save();
            }

            return $this->recalculateOrderTotal
                ->handle($lockedOrder)
                ->load(['items.product', 'tableSession.table']);
        });

        PosStateChanged::dispatch((int) $updatedOrder->store_id, (int) $updatedOrder->tableSession->table_id, 'order.items-added');

        return $updatedOrder;
    }
}
