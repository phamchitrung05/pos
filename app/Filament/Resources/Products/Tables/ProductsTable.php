<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsTable
{
    /** Hiển thị thực đơn với nhóm, giá và trạng thái bán. */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Tên món')->searchable()->sortable(),
                TextColumn::make('productGroup.name')->label('Nhóm')->sortable(),
                TextColumn::make('price')->label('Giá bán')->money('VND')->sortable(),
                IconColumn::make('is_active')->label('Đang bán')->boolean(),
                IconColumn::make('is_sku')->label('SKU')->boolean(),
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
