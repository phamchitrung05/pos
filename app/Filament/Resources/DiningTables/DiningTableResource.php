<?php

namespace App\Filament\Resources\DiningTables;

use App\Filament\Resources\DiningTables\Pages\ListDiningTables;
use App\Filament\Resources\DiningTables\Schemas\DiningTableForm;
use App\Filament\Resources\DiningTables\Tables\DiningTablesTable;
use App\Models\DiningTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DiningTableResource extends Resource
{
    /** Khai báo tường minh quan hệ Store -> bàn để Filament scope và tự gán tenant khi tạo. */
    protected static ?string $tenantRelationshipName = 'diningTables';

    protected static ?string $model = DiningTable::class;

    protected static ?string $modelLabel = 'Bàn';

    protected static ?string $pluralModelLabel = 'Bàn';

    protected static ?string $navigationLabel = 'Danh sách bàn';

    protected static string|\UnitEnum|null $navigationGroup = 'Quản lý bàn';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    public static function form(Schema $schema): Schema
    {
        return DiningTableForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DiningTablesTable::configure($table);
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
            'index' => ListDiningTables::route('/'),
        ];
    }

    /**
     * Ghép thêm mục navigation của trang "Sơ Đồ Bàn" (ManageTableSessions)
     * vào sidebar, cùng nhóm "Quản lý bàn".
     *
     * Mặc định Filament chỉ đăng ký MỘT item navigation cho mỗi resource
     * (trỏ tới trang index). Vì custom resource page không tự xuất hiện
     * trên sidebar, ta append `getNavigationItems()` của trang vào đây —
     * nhãn/nhóm/icon/sort lấy từ các thuộc tính của trang.
     */
    public static function getNavigationItems(): array
    {
        return [
            ...parent::getNavigationItems(),
        ];
    }
}
