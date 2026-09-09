<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\AssignsCurrentStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Đơn hàng phát sinh trong một phiên phục vụ. */
class Order extends Model
{
    use AssignsCurrentStore, HasFactory;

    /**
     * `total` không mass assignable vì luôn phải được tính lại từ các dòng món ở server.
     * `created_by` cũng chỉ do action mở bàn lấy từ tài khoản đã xác thực.
     */
    protected $fillable = ['store_id', 'table_session_id', 'code', 'status', 'notes'];

    /** Đồng bộ tenant từ phiên bàn và từ chối liên kết đơn hàng chéo chi nhánh. */
    protected static function booted(): void
    {
        static::saving(function (Order $order): void {
            $sessionStoreId = TableSession::query()->whereKey($order->table_session_id)->value('store_id');

            if ($sessionStoreId === null || ($order->store_id !== null && (int) $order->store_id !== (int) $sessionStoreId)) {
                throw ValidationException::withMessages([
                    'table_session_id' => 'Phiên bàn không thuộc cửa hàng đang sở hữu đơn hàng.',
                ]);
            }

            $order->store_id = $sessionStoreId;

            // Sinh mã sau khi đã suy ra store_id từ phiên bàn, hỗ trợ cả API/command ngoài Filament.
            if (blank($order->code)) {
                $order->code = 'ORD-'.now()->format('mdy').'-'.str_pad((string) static::nextSequenceNumber($sessionStoreId), 3, '0', STR_PAD_LEFT);
            }
        });
    }

    /** Lấy số thứ tự kế tiếp theo Store và ngày bằng khóa dòng sequence. */
    private static function nextSequenceNumber(int|string $storeId): int
    {
        $today = today()->toDateString();

        return (int) DB::transaction(function () use ($storeId, $today): int {
            // Tạo dòng sequence đầu tiên nếu Store chưa có order trong ngày này.
            DB::table('order_sequences')->insertOrIgnore([
                'store_id' => $storeId,
                'sequence_date' => $today,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Khóa dòng sequence để hai thiết bị không nhận cùng một số thứ tự.
            $sequence = DB::table('order_sequences')
                ->where('store_id', $storeId)
                ->where('sequence_date', $today)
                ->lockForUpdate()
                ->first();
            $nextNumber = $sequence->last_number + 1;

            DB::table('order_sequences')
                ->where('id', $sequence->id)
                ->update(['last_number' => $nextNumber, 'updated_at' => now()]);

            return $nextNumber;
        });
    }

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'status' => OrderStatus::class,
        ];
    }

    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    /** Cửa hàng sở hữu đơn hàng, dùng để giới hạn truy vấn trong đúng tenant. */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    /** Nhân viên đã khởi tạo order cùng lúc với việc mở phiên bàn. */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
