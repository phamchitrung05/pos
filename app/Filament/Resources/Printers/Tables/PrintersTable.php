<?php

namespace App\Filament\Resources\Printers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PrintersTable
{
    /** Hiển thị máy in theo chi nhánh, loại và trạng thái kết nối. */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Tên máy in')->searchable()->sortable(),
                TextColumn::make('store.name')->label('Chi nhánh')->sortable(),
                TextColumn::make('printer_type')->label('Loại')->badge(),
                TextColumn::make('ip_address')->label('Địa chỉ IP'),
                TextColumn::make('port')->label('Cổng'),
                IconColumn::make('is_active')->label('Hoạt động')->boolean(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
