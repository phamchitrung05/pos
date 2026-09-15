<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Order;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Size;
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
                ViewAction::make()
                    ->iconButton()
                    ->tooltip('Xem chi tiết')
                    ->size(Size::Medium)
                    ->color(Color::Orange)
                    ->modalHeading('Chi tiết đơn hàng')
                    ->modalWidth('7xl')
                    ->modalContent(fn (Order $record) => view('filament.resources.orders.view-modal', [
                        'record' => $record,
                    ]))
                    ->schema([]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
