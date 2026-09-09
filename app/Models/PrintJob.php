<?php

namespace App\Models;

use App\Enums\PrintJobStatus;
use App\Enums\PrintType;
use App\Models\Concerns\AssignsCurrentStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/** Lịch sử một nội dung được gửi tới máy in và trạng thái xử lý của nó. */
class PrintJob extends Model
{
    use AssignsCurrentStore, HasFactory;

    /** Cho phép ghi lệnh in; `store_id` phải lấy từ tenant đã xác thực, không tin dữ liệu client. */
    protected $fillable = ['store_id', 'printer_id', 'order_id', 'print_type', 'status', 'attempts', 'error_message', 'payload', 'printed_at'];

    /**
     * Đồng bộ tenant từ máy in và xác nhận đơn hàng tùy chọn nằm cùng Store.
     */
    protected static function booted(): void
    {
        static::saving(function (PrintJob $printJob): void {
            $printerStoreId = Printer::query()->whereKey($printJob->printer_id)->value('store_id');
            $orderStoreId = $printJob->order_id === null
                ? $printerStoreId
                : Order::query()->whereKey($printJob->order_id)->value('store_id');

            if ($printerStoreId === null || $orderStoreId === null || (int) $printerStoreId !== (int) $orderStoreId) {
                throw ValidationException::withMessages([
                    'order_id' => 'Máy in và đơn hàng phải thuộc cùng một cửa hàng.',
                ]);
            }

            if ($printJob->store_id !== null && (int) $printJob->store_id !== (int) $printerStoreId) {
                throw ValidationException::withMessages([
                    'printer_id' => 'Máy in không thuộc cửa hàng đang sở hữu lệnh in.',
                ]);
            }

            $printJob->store_id = $printerStoreId;
        });
    }

    protected function casts(): array
    {
        return [
            'print_type' => PrintType::class,
            'status' => PrintJobStatus::class,
            'attempts' => 'integer',
            'payload' => 'array',
            'printed_at' => 'datetime',
        ];
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    /** Cửa hàng sở hữu lệnh in, dùng để chặn truy cập máy in chéo tenant. */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
