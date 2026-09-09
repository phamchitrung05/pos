<?php

namespace App\Filament\Resources\OrderItems\Schemas;

use App\Models\Order;
use App\Models\Product;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class OrderItemForm
{
    /** Khai báo form chi tiết món và giá snapshot tại thời điểm gọi. */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Cả đơn hàng và sản phẩm phải thuộc tenant hiện tại. Việc
                // kiểm tra hai khóa ngăn ghép sản phẩm Store A vào đơn Store B.
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
                Select::make('product_id')
                    ->label('Sản phẩm')
                    ->options(fn () => Product::query()->where('store_id', Filament::getTenant()?->getKey())->pluck('name', 'id'))
                    ->scopedExists(
                        Product::class,
                        'id',
                        fn (Builder $query): Builder => $query->where('store_id', Filament::getTenant()?->getKey()),
                    )
                    ->required()
                    ->searchable(),
                TextInput::make('quantity')->label('Số lượng')->numeric()->integer()->minValue(1)->default(1)->required(),
                // Giá chỉ hiển thị để đối chiếu; action lấy snapshot trực tiếp từ Product phía server.
                TextInput::make('unit_price')->label('Đơn giá')->numeric()->prefix('₫')->disabled()->dehydrated(false),
                Textarea::make('notes')->label('Ghi chú món')->columnSpanFull(),
            ]);
    }
}
