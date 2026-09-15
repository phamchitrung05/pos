<?php

namespace App\Filament\Resources\TableSessions\Tables;

use App\Enums\TableSessionStatus;
use App\Models\TableSession;
use App\Queries\Pos\TableSessionActivityReadModel;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TableSessionsTable
{
    /** Danh sách phiên bàn với thời gian và trạng thái phục vụ. */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('table.name')->label('Bàn')->sortable(),
                TextColumn::make('status')->label('Trạng thái')->badge(),
                TextColumn::make('start_time')->label('Bắt đầu')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('end_time')->label('Kết thúc')->dateTime('d/m/Y H:i')->placeholder('Đang mở'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(TableSessionStatus::class)
                    ->native(false),
                SelectFilter::make('table')
                    ->label('Bàn')
                    ->relationship('table', 'name')
                    ->native(false),
                Filter::make('start_time')
                    ->label('Khoảng ngày bắt đầu')
                    ->form([
                        DatePicker::make('from')->label('Từ ngày'),
                        DatePicker::make('until')->label('Đến ngày'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('start_time', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('start_time', '<=', $date));
                    }),
            ])
            ->recordActions([
                Action::make('view')
                    ->iconButton()
                    ->tooltip('Xem chi tiết')
                    ->icon(Heroicon::OutlinedEye)
                    ->size(Size::Medium)
                    ->color(Color::Orange)
                    ->modalHeading('')
                    ->modalWidth('7xl')

                    ->extraModalWindowAttributes(['class' => 'order-view-modal-window s960'])
                    ->modalContent(function (TableSession $record) {
                        $record->load(['table.zone', 'openedBy', 'order.items', 'order.payments']);
                        $events = app(TableSessionActivityReadModel::class)->for($record);

                        return view('filament.resources.table-sessions.view-modal', [
                            'record' => $record,
                            'events' => $events,
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Đóng'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
