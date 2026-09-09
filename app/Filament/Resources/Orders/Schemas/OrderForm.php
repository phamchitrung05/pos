<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use App\Models\TableSession;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class OrderForm
{
    /** Khai báo form quản lý trạng thái và tổng tiền của đơn hàng. */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Chỉ liên kết đơn với phiên bàn cùng tenant và xác thực lại
                // store_id trên server để ngăn request chéo chi nhánh.
                Select::make('table_session_id')
                    ->label('Phiên bàn')
                    ->options(fn () => TableSession::query()->where('store_id', Filament::getTenant()?->getKey())->pluck('id', 'id'))
                    ->scopedExists(
                        TableSession::class,
                        'id',
                        fn (Builder $query): Builder => $query->where('store_id', Filament::getTenant()?->getKey()),
                    )
                    ->required()
                    ->searchable(),
                Select::make('status')->label('Trạng thái')->options(OrderStatus::class)->required()->default(OrderStatus::Open->value),
                // Tổng tiền chỉ để xem; RecalculateOrderTotal là nơi duy nhất ghi giá trị này.
                TextInput::make('total')->label('Tổng tiền')->numeric()->prefix('₫')->disabled()->dehydrated(false),
                Textarea::make('notes')->label('Ghi chú')->columnSpanFull(),
            ]);
    }
}
