<?php

namespace App\Models;

use App\Enums\PrinterType;
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
    protected $fillable = ['store_id', 'printer_id', 'order_id', 'payment_id', 'print_type', 'status', 'attempts', 'error_message', 'payload', 'printed_at'];

    /** Claim token là bí mật nội bộ, tuyệt đối không xuất hiện khi serialize model. */
    protected $hidden = ['claim_token_hash'];

    /**
     * Đồng bộ tenant từ máy in và xác nhận đơn hàng tùy chọn nằm cùng Store.
     */
    protected static function booted(): void
    {
        static::saving(function (PrintJob $printJob): void {
            $printer = Printer::query()->whereKey($printJob->printer_id)->first(['store_id', 'printer_type']);
            $printerStoreId = $printer?->store_id;
            $orderStoreId = $printJob->order_id === null
                ? $printerStoreId
                : Order::query()->whereKey($printJob->order_id)->value('store_id');
            $paymentStoreId = $printJob->payment_id === null
                ? $printerStoreId
                : Payment::query()->whereKey($printJob->payment_id)->value('store_id');

            if ($printerStoreId === null
                || $orderStoreId === null
                || $paymentStoreId === null
                || (int) $printerStoreId !== (int) $orderStoreId
                || (int) $printerStoreId !== (int) $paymentStoreId) {
                throw ValidationException::withMessages([
                    'order_id' => 'Máy in, đơn hàng và thanh toán phải thuộc cùng một cửa hàng.',
                ]);
            }

            if ($printJob->store_id !== null && (int) $printJob->store_id !== (int) $printerStoreId) {
                throw ValidationException::withMessages([
                    'printer_id' => 'Máy in không thuộc cửa hàng đang sở hữu lệnh in.',
                ]);
            }

            $printType = $printJob->print_type instanceof PrintType
                ? $printJob->print_type->value
                : (string) $printJob->print_type;
            $printerType = $printer?->printer_type instanceof PrinterType
                ? $printer->printer_type->value
                : (string) $printer?->printer_type;

            if ($printType !== $printerType && $printerType !== PrinterType::Both->value) {
                throw ValidationException::withMessages([
                    'print_type' => 'Loại nội dung phải khớp với loại máy in nhận lệnh.',
                ]);
            }

            if ($printJob->payment_id !== null
                && (int) Payment::query()->whereKey($printJob->payment_id)->value('order_id') !== (int) $printJob->order_id) {
                throw ValidationException::withMessages([
                    'payment_id' => 'Thanh toán và lệnh in phải tham chiếu cùng một order.',
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
            'claimed_at' => 'datetime',
            'lease_expires_at' => 'datetime',
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

    /** Payment gốc giúp mỗi giao dịch chỉ tạo một hóa đơn receipt. */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
