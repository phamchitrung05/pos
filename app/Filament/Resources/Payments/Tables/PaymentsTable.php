<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(PaymentStatus::class)
                    ->native(false),
                SelectFilter::make('payment_method')
                    ->label('Phương thức')
                    ->options(PaymentMethod::class)
                    ->native(false),
                Filter::make('created_at')
                    ->label('Khoảng ngày giao dịch')
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
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
