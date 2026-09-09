<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\AssignsCurrentStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/** Một lần thanh toán, hỗ trợ thanh toán nhiều đợt cho cùng một đơn. */
class Payment extends Model
{
    use AssignsCurrentStore, HasFactory;

    const UPDATED_AT = null;

    /**
     * Không khai báo số tiền, phương thức, người thu hoặc idempotency key trong
     * `$fillable`: toàn bộ các giá trị này phải do CheckoutTable xác lập.
     */
    protected $fillable = ['store_id', 'order_id', 'status', 'paid_at'];

    /** Đồng bộ tenant từ đơn hàng để dữ liệu thanh toán không thể bị chuyển chéo Store. */
    protected static function booted(): void
    {
        static::saving(function (Payment $payment): void {
            $orderStoreId = Order::query()->whereKey($payment->order_id)->value('store_id');

            if ($orderStoreId === null || ($payment->store_id !== null && (int) $payment->store_id !== (int) $orderStoreId)) {
                throw ValidationException::withMessages([
                    'order_id' => 'Đơn hàng không thuộc cửa hàng đang ghi nhận thanh toán.',
                ]);
            }

            $payment->store_id = $orderStoreId;
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Cửa hàng sở hữu thanh toán, dùng để giới hạn đối soát trong đúng tenant. */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** Nhân viên đã xác nhận nhận tiền tại thời điểm checkout. */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
