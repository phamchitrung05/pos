<?php

namespace App\Models;

use App\Enums\TableSessionStatus;
use App\Models\Concerns\AssignsCurrentStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

/** Phiên phục vụ của khách tại một bàn, thường kết thúc khi thanh toán xong. */
class TableSession extends Model
{
    use AssignsCurrentStore, HasFactory;

    protected $table = 'table_sessions';

    /**
     * Các trường nghiệp vụ có thể nhập; khóa audit chỉ được action phía server gán bằng `forceCreate()`.
     */
    protected $fillable = ['store_id', 'table_id', 'start_time', 'end_time', 'status'];

    /** Đồng bộ tenant từ bàn và chặn việc di chuyển phiên đang có sang Store khác. */
    protected static function booted(): void
    {
        static::saving(function (TableSession $session): void {
            $tableStoreId = DiningTable::query()->whereKey($session->table_id)->value('store_id');

            if ($tableStoreId === null || ($session->store_id !== null && (int) $session->store_id !== (int) $tableStoreId)) {
                throw ValidationException::withMessages([
                    'table_id' => 'Bàn được chọn không thuộc cửa hàng đang sở hữu phiên.',
                ]);
            }

            $session->store_id = $tableStoreId;

            // Gán tường minh trạng thái mặc định trước khi INSERT; nếu chờ default
            // của database thì active_table_id sẽ bị để null và làm mất tác dụng unique.
            $session->status ??= TableSessionStatus::Open;

            // Cột sentinel chỉ giữ table_id khi phiên mở; unique index chặn hai request mở cùng bàn.
            $session->active_table_id = $session->status === TableSessionStatus::Open
                ? $session->table_id
                : null;
        });
    }

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'status' => TableSessionStatus::class,
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    /** Cửa hàng sở hữu phiên bàn, dùng để giới hạn truy vấn trong đúng tenant. */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** Order chính duy nhất được tạo cùng lúc khi mở phiên bàn. */
    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    /** Nhân viên đã mở phiên bàn; quan hệ có thể null nếu tài khoản đã bị xóa. */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /** Nhân viên đã hoàn tất thanh toán và đóng phiên bàn. */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
