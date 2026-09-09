<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Loại nội dung được đóng băng trong payload của một lệnh in. */
enum PrintType: string implements HasLabel
{
    case Kitchen = 'kitchen';
    case Receipt = 'receipt';
    case Label = 'label';

    /** Nhãn hiển thị giúp form không phải duy trì một danh sách chuỗi riêng. */
    public function getLabel(): string
    {
        return match ($this) {
            self::Kitchen => 'Phiếu bếp',
            self::Receipt => 'Hóa đơn',
            self::Label => 'Tem nhãn',
        };
    }
}
