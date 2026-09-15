<?php

namespace App\Filament\Resources\OrderItems\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\IconSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrderItemsTable
{
    /** Danh sách từng dòng món trong các đơn hàng. */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_id')->label('Mã đơn')->sortable(),
                TextColumn::make('product.name')->label('Sản phẩm')->searchable()->sortable(),
                TextColumn::make('quantity')->label('Số lượng')->sortable(),
                TextColumn::make('unit_price')->label('Đơn giá')->money('VND')->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Chỉnh sửa')
                    ->iconSize(IconSize::Medium)
                    ->color(Color::Orange),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
