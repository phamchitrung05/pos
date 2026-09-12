<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Khổ giấy quyết định chiều rộng bitmap ESC/POS gửi tới máy in nhiệt. */
enum PrinterPaperWidth: int implements HasLabel
{
    case Mm58 = 58;
    case Mm80 = 80;

    public function getLabel(): string
    {
        return "{$this->value} mm";
    }

    /** Số điểm phổ biến ở mật độ 203 DPI của từng khổ giấy. */
    public function dotsPerLine(): int
    {
        return match ($this) {
            self::Mm58 => 384,
            self::Mm80 => 576,
        };
    }
}
