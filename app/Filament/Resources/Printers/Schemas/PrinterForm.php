<?php

namespace App\Filament\Resources\Printers\Schemas;

use App\Enums\PrinterPaperWidth;
use App\Enums\PrinterType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PrinterForm
{
    /** Khai báo form cấu hình máy in mạng của chi nhánh. */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Máy in luôn nhận store_id từ tenant hiện tại; không tin ID
                // chi nhánh do client gửi trong request tạo hoặc cập nhật.
                TextInput::make('name')->label('Tên máy in')->required()->maxLength(255),
                Select::make('printer_type')->label('Loại máy in')->options(PrinterType::class)->required()->default(PrinterType::Receipt->value),
                // Agent chỉ claim máy có endpoint LAN hoàn chỉnh, vì vậy form không tạo cấu hình nửa vời.
                TextInput::make('ip_address')->label('Địa chỉ IP')->ip()->required(),
                TextInput::make('port')->label('Cổng kết nối')->numeric()->integer()->minValue(1)->maxValue(65535)->required(),
                Select::make('paper_width_mm')->label('Khổ giấy')->options(PrinterPaperWidth::class)->required()->default(PrinterPaperWidth::Mm80->value),
                TextInput::make('kitchen_copies')->label('Số bản phiếu bếp')->numeric()->integer()->minValue(1)->maxValue(5)->required()->default(1),
                Toggle::make('is_active')->label('Đang sử dụng')->default(true),
            ]);
    }
}
