<?php

namespace App\Filament\Resources\TableZones\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\IconSize;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TableZonesTable
{
    /** Danh sách khu vực bàn và chi nhánh sở hữu. */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Tên khu vực')->searchable()->sortable(),
                TextColumn::make('store.name')->label('Chi nhánh')->sortable(),
                IconColumn::make('is_active')->label('Hoạt động')->boolean(),
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
