<?php

namespace App\Filament\Resources\PrintJobs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PrintJobsTable
{
    /** Danh sách lịch sử in để theo dõi và xử lý các lệnh lỗi. */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('printer.name')->label('Máy in')->sortable(),
                TextColumn::make('order_id')->label('Mã đơn')->sortable(),
                TextColumn::make('print_type')->label('Loại')->badge(),
                TextColumn::make('status')->label('Trạng thái')->badge(),
                TextColumn::make('attempts')->label('Số lần thử')->sortable(),
                TextColumn::make('printed_at')->label('Đã in lúc')->dateTime('d/m/Y H:i')->placeholder('Chưa in'),
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
