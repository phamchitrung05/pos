<?php

namespace App\Filament\Resources\ProductGroups\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductGroupForm
{
    /** Khai báo form tạo và chỉnh sửa nhóm sản phẩm. */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Filament tự gán store_id từ tenant trên URL, vì vậy form
                // không mở quyền lựa chọn hoặc thay đổi chi nhánh sở hữu.
                TextInput::make('name')->label('Tên nhóm')->required()->maxLength(255),
                TextInput::make('icon')->label('Icon')->maxLength(255),
                TextInput::make('sort_order')->label('Thứ tự')->numeric()->default(0)->required(),
                Toggle::make('is_active')->label('Đang hiển thị')->default(true),
            ]);
    }
}
