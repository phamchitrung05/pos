<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersTable
{
    /** Hiển thị đơn hàng theo phiên bàn, trạng thái và tổng tiền. */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Mã đơn')->searchable()->sortable(),
                TextColumn::make('tableSession.table.name')->label('Bàn')->sortable(),
                TextColumn::make('status')->label('Trạng thái')->badge(),
                TextColumn::make('total')->label('Tổng tiền')->money('VND')->sortable(),
                TextColumn::make('created_at')->label('Thời gian')->dateTime('d/m/Y H:i')->sortable(),
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
