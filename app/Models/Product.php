<?php

namespace App\Models;

use App\Models\Concerns\AssignsCurrentStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/** Món hoặc sản phẩm được bán trong thực đơn. */
class Product extends Model
{
    use AssignsCurrentStore, HasFactory;

    protected $table = 'product';

    /** Cho phép ghi thuộc tính sản phẩm; `store_id` phải lấy từ tenant đã xác thực, không tin dữ liệu client. */
    protected $fillable = ['store_id', 'product_group_id', 'name', 'description', 'price', 'is_active', 'is_sku'];

    /** Đồng bộ tenant từ nhóm sản phẩm và từ chối chuyển sản phẩm sang Store khác bằng payload giả. */
    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            // MVP không hỗ trợ order miễn phí hoặc giá âm vì checkout luôn phải tạo payment dương.
            if ((float) $product->price <= 0) {
                throw ValidationException::withMessages([
                    'price' => 'Giá bán phải lớn hơn 0.',
                ]);
            }

            $groupStoreId = ProductGroup::query()->whereKey($product->product_group_id)->value('store_id');

            if ($groupStoreId === null || ($product->store_id !== null && (int) $product->store_id !== (int) $groupStoreId)) {
                throw ValidationException::withMessages([
                    'product_group_id' => 'Nhóm sản phẩm không thuộc cửa hàng đang sở hữu sản phẩm.',
                ]);
            }

            $product->store_id = $groupStoreId;
        });
    }

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'is_active' => 'boolean', 'is_sku' => 'boolean'];
    }

    public function productGroup(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class);
    }

    /** Cửa hàng sở hữu sản phẩm, dùng để giới hạn truy vấn trong đúng tenant. */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
