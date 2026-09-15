<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Concerns\IsPosTransactionReadOnly;
use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Filament\Resources\Payments\Pages\EditPayment;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Schemas\PaymentForm;
use App\Filament\Resources\Payments\Tables\PaymentsTable;
use App\Models\Payment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PaymentResource extends Resource
{
    use IsPosTransactionReadOnly;

    /** Thanh toán được scope theo Store trực tiếp để bảo vệ dữ liệu đối soát. */
    protected static ?string $tenantRelationshipName = 'payments';

    protected static ?string $model = Payment::class;

    protected static ?string $modelLabel = 'Thanh toán';

    protected static ?string $pluralModelLabel = 'Thanh toán';

    protected static ?string $navigationLabel = 'Thanh toán';

    protected static string|\UnitEnum|null $navigationGroup = 'Đơn hàng';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    public static function form(Schema $schema): Schema
    {
        return PaymentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
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
            'index' => ListPayments::route('/'),
            'create' => CreatePayment::route('/create'),
            'edit' => EditPayment::route('/{record}/edit'),
        ];
    }

    /**
     * Ghép thêm mục navigation của trang lịch sử thanh toán
     * vào sidebar, cùng nhóm "Đơn hàng".
     *
     * Mặc định Filament chỉ đăng ký MỘT item navigation cho mỗi resource
     * (trỏ tới trang index). Vì custom resource page không tự xuất hiện
     * trên sidebar, ta append `getNavigationItems()` của trang vào đây —
     * nhãn/nhóm/icon/sort lấy từ các thuộc tính của trang (pattern giống
     * "Sơ đồ bàn" của DiningTableResource và lịch sử đơn hàng).
     */
    public static function getNavigationItems(): array
    {
        return [
            ...parent::getNavigationItems(),
        ];
    }
}
