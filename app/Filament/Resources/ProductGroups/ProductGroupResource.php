<?php

namespace App\Filament\Resources\ProductGroups;

use App\Filament\Resources\ProductGroups\Pages\CreateProductGroup;
use App\Filament\Resources\ProductGroups\Pages\EditProductGroup;
use App\Filament\Resources\ProductGroups\Pages\ListProductGroups;
use App\Filament\Resources\ProductGroups\Schemas\ProductGroupForm;
use App\Filament\Resources\ProductGroups\Tables\ProductGroupsTable;
use App\Models\ProductGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductGroupResource extends Resource
{
    /** Khai báo quan hệ ngược dùng để giới hạn nhóm sản phẩm theo Store hiện tại. */
    protected static ?string $tenantRelationshipName = 'productGroups';

    protected static ?string $model = ProductGroup::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Products';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquare3Stack3d;

    public static function form(Schema $schema): Schema
    {
        return ProductGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductGroupsTable::configure($table);
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
            'index' => ListProductGroups::route('/'),
            'create' => CreateProductGroup::route('/create'),
            'edit' => EditProductGroup::route('/{record}/edit'),
        ];
    }
}
