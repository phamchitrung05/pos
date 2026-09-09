<?php

namespace App\Models;

use App\Models\Concerns\AssignsCurrentStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/** Một dòng sản phẩm trong đơn, lưu cả giá tại thời điểm gọi món. */
class OrderItem extends Model
{
    use AssignsCurrentStore, HasFactory;

    /**
     * `unit_price` và dấu mốc in bếp không mass assignable để client không thể
     * tự sửa giá snapshot hoặc giả vờ một món đã được chuyển xuống bếp.
     */
    protected $fillable = ['store_id', 'order_id', 'product_id', 'quantity', 'notes'];

    /**
     * Bảo đảm đơn hàng, sản phẩm và dòng món cùng một Store.
     *
     * Đây là kiểm tra quan trọng vì OrderItem có hai khóa ngoại độc lập; chỉ
     * scope danh sách trên giao diện không đủ để chống request bị chỉnh sửa.
     */
    protected static function booted(): void
    {
        static::saving(function (OrderItem $item): void {
            $orderStoreId = Order::query()->whereKey($item->order_id)->value('store_id');
            $productStoreId = Product::query()->whereKey($item->product_id)->value('store_id');

            if ($orderStoreId === null || $productStoreId === null || (int) $orderStoreId !== (int) $productStoreId) {
                throw ValidationException::withMessages([
                    'product_id' => 'Sản phẩm và đơn hàng phải thuộc cùng một cửa hàng.',
                ]);
            }

            if ($item->store_id !== null && (int) $item->store_id !== (int) $orderStoreId) {
                throw ValidationException::withMessages([
                    'order_id' => 'Đơn hàng không thuộc cửa hàng đang sở hữu dòng món.',
                ]);
            }

            $item->store_id = $orderStoreId;
        });
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'kitchen_printed_quantity' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Cửa hàng sở hữu dòng món, dùng để giới hạn truy vấn trong đúng tenant. */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
