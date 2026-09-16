<?php

namespace App\Filament\Resources\TableZones;

use App\Filament\Resources\TableZones\Pages\ListTableZones;
use App\Filament\Resources\TableZones\Schemas\TableZoneForm;
use App\Filament\Resources\TableZones\Tables\TableZonesTable;
use App\Models\TableZone;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TableZoneResource extends Resource
{
    /** Tên quan hệ ngược trên Store khác tên mặc định `tableZones`, cần khai báo rõ cho Filament tenancy. */
    protected static ?string $tenantRelationshipName = 'zones';

    protected static ?string $model = TableZone::class;

    protected static ?string $modelLabel = 'Khu vực bàn';

    protected static ?string $pluralModelLabel = 'Khu vực bàn';

    protected static ?string $navigationLabel = 'Khu vực bàn';

    protected static string|\UnitEnum|null $navigationGroup = 'Cửa hàng';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    public static function form(Schema $schema): Schema
    {
        return TableZoneForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TableZonesTable::configure($table);
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
            'index' => ListTableZones::route('/'),
        ];
    }
}
