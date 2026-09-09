<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Trạng thái xử lý của một bản ghi thanh toán. */
enum PaymentStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Refunded = 'refunded';

    /** Trả về nhãn nghiệp vụ thay vì để từng màn hình tự ánh xạ trạng thái. */
    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Đang chờ',
            self::Completed => 'Đã thanh toán',
            self::Failed => 'Thất bại',
            self::Refunded => 'Đã hoàn tiền',
        };
    }
}
