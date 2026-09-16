<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Models\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    /** Sản phẩm có store_id trực tiếp để Filament tự động scope và gán tenant an toàn. */
    protected static ?string $tenantRelationshipName = 'products';

    protected static ?string $model = Product::class;

    protected static ?string $modelLabel = 'Sản phẩm';

    protected static ?string $pluralModelLabel = 'Sản phẩm';

    protected static ?string $navigationLabel = 'Sản phẩm';

    protected static string|\UnitEnum|null $navigationGroup = 'Sản phẩm';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            // KHÔNG đăng ký trang 'create': nhờ đó nút "Tạo mới" ở danh sách
            // mở popup (modal) thay vì điều hướng sang trang riêng.
            'index' => ListProducts::route('/'),
        ];
    }
}
