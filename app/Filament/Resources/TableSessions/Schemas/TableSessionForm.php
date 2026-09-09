<?php

namespace App\Filament\Resources\TableSessions\Schemas;

use App\Enums\TableSessionStatus;
use App\Models\DiningTable;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class TableSessionForm
{
    /** Khai báo form theo dõi thời gian và trạng thái phiên bàn. */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Chỉ cho chọn bàn thuộc Store hiện tại và xác thực lại trên
                // server để payload giả không thể liên kết chéo chi nhánh.
                Select::make('table_id')
                    ->label('Bàn')
                    ->options(fn () => DiningTable::query()->where('store_id', Filament::getTenant()?->getKey())->pluck('name', 'id'))
                    ->scopedExists(
                        DiningTable::class,
                        'id',
                        fn (Builder $query): Builder => $query->where('store_id', Filament::getTenant()?->getKey()),
                    )
                    ->required()
                    ->searchable(),
                DateTimePicker::make('start_time')->label('Bắt đầu'),
                DateTimePicker::make('end_time')->label('Kết thúc'),
                Select::make('status')->label('Trạng thái')->options(TableSessionStatus::class)->required()->default(TableSessionStatus::Open->value),
            ]);
    }
}
