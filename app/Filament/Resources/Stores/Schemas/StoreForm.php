<?php

namespace App\Filament\Resources\Stores\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class StoreForm
{
    /** Khai báo các trường nhập liệu cho thông tin chi nhánh. */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Tên chi nhánh')->required()->maxLength(255),
                Textarea::make('address')->label('Địa chỉ')->columnSpanFull(),
                TextInput::make('phone')->label('Số điện thoại')->tel()->maxLength(20),
                TextInput::make('email')->label('Email')->email(),
                TextInput::make('opening_hours')->label('Giờ mở cửa')->placeholder('08:00 - 22:00'),
                Toggle::make('is_active')->label('Đang hoạt động')->default(true),
            ]);
    }
}
