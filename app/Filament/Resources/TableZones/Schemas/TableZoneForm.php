<?php

namespace App\Filament\Resources\TableZones\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TableZoneForm
{
    /** Khai báo form khu vực bàn theo từng chi nhánh. */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // store_id được Filament gán từ tenant hiện tại, không cho
                // trình duyệt gửi một Store khác để vượt ranh giới dữ liệu.
                TextInput::make('name')->label('Tên khu vực')->required()->maxLength(100),
                Toggle::make('is_active')->label('Đang sử dụng')->default(true),
            ]);
    }
}
