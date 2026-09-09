<?php

namespace App\Filament\Resources\DiningTables\Schemas;

use App\Models\TableZone;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class DiningTableForm
{
    /** Khai báo form bàn ăn và khu vực tùy chọn của bàn. */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // store_id được Filament tự gán. Danh sách khu vực và rule
                // exists đều bị khóa vào tenant để chống sửa request thủ công.
                Select::make('zone_id')
                    ->label('Khu vực')
                    ->options(fn () => TableZone::query()->where('store_id', Filament::getTenant()?->getKey())->pluck('name', 'id'))
                    ->scopedExists(
                        TableZone::class,
                        'id',
                        fn (Builder $query): Builder => $query->where('store_id', Filament::getTenant()?->getKey()),
                    )
                    ->searchable(),
                TextInput::make('name')->label('Tên hoặc số bàn')->required()->maxLength(255),
            ]);
    }
}
