<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Concerns\IsPosTransactionReadOnly;
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    use IsPosTransactionReadOnly;

    /** Đơn hàng được cô lập trực tiếp bằng Store thay vì chỉ dựa vào chuỗi phiên bàn. */
    protected static ?string $tenantRelationshipName = 'orders';

    protected static ?string $model = Order::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Orders';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
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
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }

    /**
     * Ghép thêm mục navigation của trang "History Order" (HistoryOrder)
     * vào sidebar, cùng nhóm "Orders".
     *
     * Mặc định Filament chỉ đăng ký MỘT item navigation cho mỗi resource
     * (trỏ tới trang index). Vì custom resource page không tự xuất hiện
     * trên sidebar, ta append `getNavigationItems()` của trang vào đây —
     * nhãn/nhóm/icon/sort lấy từ các thuộc tính của trang (pattern giống
     * "Sơ Đồ Bàn" của DiningTableResource).
     */
    public static function getNavigationItems(): array
    {
        return [
            ...parent::getNavigationItems(),
        ];
    }
}
