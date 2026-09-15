<?php

namespace App\Filament\Resources\OrderItems;

use App\Filament\Resources\Concerns\IsPosTransactionReadOnly;
use App\Filament\Resources\OrderItems\Pages\CreateOrderItem;
use App\Filament\Resources\OrderItems\Pages\EditOrderItem;
use App\Filament\Resources\OrderItems\Pages\ListOrderItems;
use App\Filament\Resources\OrderItems\Schemas\OrderItemForm;
use App\Filament\Resources\OrderItems\Tables\OrderItemsTable;
use App\Models\OrderItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OrderItemResource extends Resource
{
    use IsPosTransactionReadOnly;

    /** Dòng món được scope trực tiếp theo Store để không thể xem chi tiết đơn của tenant khác. */
    protected static ?string $tenantRelationshipName = 'orderItems';

    protected static ?string $model = OrderItem::class;

    protected static ?string $modelLabel = 'Dòng món';

    protected static ?string $pluralModelLabel = 'Dòng món';

    protected static ?string $navigationLabel = 'Dòng món';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\UnitEnum|null $navigationGroup = 'Đơn hàng';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    public static function form(Schema $schema): Schema
    {
        return OrderItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrderItemsTable::configure($table);
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
            'index' => ListOrderItems::route('/'),
            'create' => CreateOrderItem::route('/create'),
            'edit' => EditOrderItem::route('/{record}/edit'),
        ];
    }
}
