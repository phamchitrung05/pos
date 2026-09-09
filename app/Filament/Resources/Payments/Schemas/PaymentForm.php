<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Enums\PaymentStatus;
use App\Models\Order;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class PaymentForm
{
    /** Khai báo form ghi nhận một lần thanh toán của đơn hàng. */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Thanh toán chỉ được gắn với đơn hàng trong tenant hiện tại;
                // server xác thực lại để bảo vệ dữ liệu đối soát.
                Select::make('order_id')
                    ->label('Đơn hàng')
                    ->options(fn () => Order::query()->where('store_id', Filament::getTenant()?->getKey())->pluck('id', 'id'))
                    ->scopedExists(
                        Order::class,
                        'id',
                        fn (Builder $query): Builder => $query->where('store_id', Filament::getTenant()?->getKey()),
                    )
                    ->required()
                    ->searchable(),
                TextInput::make('amount')->label('Số tiền')->numeric()->prefix('₫')->minValue(0)->required(),
                Select::make('status')->label('Trạng thái')->options(PaymentStatus::class)->required()->default(PaymentStatus::Pending->value),
                DateTimePicker::make('paid_at')->label('Thời điểm thanh toán'),
            ]);
    }
}
