<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Loại máy in, dùng để ngăn gửi phiếu bếp nhầm sang máy in hóa đơn. */
enum PrinterType: string implements HasLabel
{
    case Receipt = 'receipt';
    case Kitchen = 'kitchen';
    case Both = 'both';
    case Label = 'label';

    /** Nhãn tiếng Việt dùng trực tiếp bởi Select và badge của Filament. */
    public function getLabel(): string
    {
        return match ($this) {
            self::Receipt => 'Hóa đơn',
            self::Kitchen => 'Bếp',
            self::Both => 'Hóa đơn & bếp',
            self::Label => 'Tem nhãn',
        };
    }
}
