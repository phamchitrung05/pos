<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\ProductGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ProductForm
{
    /** Khai báo form nhập món và giá bán hiện tại. */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Nhóm sản phẩm phải thuộc tenant hiện tại; rule scopedExists
                // kiểm tra lại ở server thay vì chỉ giới hạn danh sách UI.
                Select::make('product_group_id')
                    ->label('Nhóm sản phẩm')
                    ->options(fn () => ProductGroup::query()->where('store_id', Filament::getTenant()?->getKey())->pluck('name', 'id'))
                    ->scopedExists(
                        ProductGroup::class,
                        'id',
                        fn (Builder $query): Builder => $query->where('store_id', Filament::getTenant()?->getKey()),
                    )
                    ->required()
                    ->searchable(),
                TextInput::make('name')->label('Tên món')->required()->maxLength(255),
                Textarea::make('description')->label('Mô tả')->columnSpanFull(),
                // Giá phải dương để mọi order đều có thể hoàn tất qua CheckoutTable.
                TextInput::make('price')->label('Giá bán')->numeric()->prefix('₫')->required()->minValue(1),
                Toggle::make('is_active')->label('Đang bán')->default(true),
                Toggle::make('is_sku')->label('Có biến thể SKU')->default(false),
            ]);
    }
}
