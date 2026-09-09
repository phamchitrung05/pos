<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Trạng thái nghiệp vụ của đơn hàng trong suốt một phiên bàn. */
enum OrderStatus: string implements HasLabel
{
    case Open = 'open';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    /** Cung cấp nhãn dùng chung cho các thành phần Filament. */
    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Đang phục vụ',
            self::Paid => 'Đã thanh toán',
            self::Cancelled => 'Đã hủy',
        };
    }
}
