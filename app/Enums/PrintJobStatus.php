<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Trạng thái truyền và thực thi lệnh in trên thiết bị POS. */
enum PrintJobStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Printing = 'printing';
    case Printed = 'printed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /** Chuẩn hóa nhãn giữa lịch sử in, form quản trị và API trong tương lai. */
    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Chờ in',
            self::Printing => 'Đang in',
            self::Printed => 'Đã in',
            self::Failed => 'Thất bại',
            self::Cancelled => 'Đã hủy',
        };
    }
}
