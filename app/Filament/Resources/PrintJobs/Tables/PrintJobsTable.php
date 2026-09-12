<?php

namespace App\Filament\Resources\PrintJobs\Tables;

use App\Enums\PrintJobStatus;
use App\Enums\PrintType;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PrintJobsTable
{
    /** Danh sách lịch sử in để theo dõi và xử lý các lệnh lỗi. */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('printer.name')->label('Máy in')->sortable(),
                TextColumn::make('order.code')->label('Mã đơn')->placeholder('Không gắn đơn')->searchable()->sortable(),
                TextColumn::make('print_type')->label('Loại')->badge(),
                TextColumn::make('status')->label('Trạng thái')->badge(),
                TextColumn::make('attempts')->label('Số lần thử')->sortable(),
                TextColumn::make('error_message')->label('Lỗi gần nhất')->limit(45)->placeholder('Không có'),
                TextColumn::make('created_at')->label('Tạo lúc')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('printed_at')->label('Đã in lúc')->dateTime('d/m/Y H:i')->placeholder('Chưa in'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Trạng thái')->options(PrintJobStatus::class),
                SelectFilter::make('print_type')->label('Loại nội dung')->options(PrintType::class),
                SelectFilter::make('printer')->label('Máy in')->relationship('printer', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }
}
