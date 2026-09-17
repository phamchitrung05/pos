<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Queries\Pos\TableMapReadModel;
use App\Queries\Pos\TableSessionActivityReadModel;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(OrderStatus::class)
                    ->native(false),
                Filter::make('created_at')
                    ->label('Khoảng ngày tạo đơn')
                    ->form([
                        DatePicker::make('from')->label('Từ ngày'),
                        DatePicker::make('until')->label('Đến ngày'),
                    ])
                    ->default([
                        'from' => today()->toDateString(),
                        'until' => today()->toDateString(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->iconButton()
                    ->tooltip('Xem chi tiết')
                    ->size(Size::Medium)
                    ->color(Color::Orange)
                    ->modalHeading('Chi tiết Order')
                    ->modalWidth('7xl')
                    ->modalContent(function (Order $record) {
                        $selectedTable = app(TableMapReadModel::class)->orderDetails($record);
                        $events = $record->tableSession
                            ? app(TableSessionActivityReadModel::class)->for($record->tableSession)
                            : collect();

                        return view('filament.resources.orders.view-modal', [
                            'selectedTable' => $selectedTable,
                            'events' => $events,
                        ]);
                    })
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalCancelActionLabel('Đóng')
                    ->schema([]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
