<?php

namespace App\Filament\Resources\Payments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsTable
{
    /** Danh sách giao dịch thanh toán để đối soát. */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.code')->label('Mã đơn')->searchable()->sortable(),
                TextColumn::make('amount')->label('Số tiền')->money('VND')->sortable(),
                TextColumn::make('status')->label('Trạng thái')->badge(),
                TextColumn::make('paid_at')->label('Đã thanh toán lúc')->dateTime('d/m/Y H:i')->placeholder('Chưa hoàn tất'),
            ])
            ->filters([
                //
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
