<?php

namespace App\Filament\Resources\TableSessions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
