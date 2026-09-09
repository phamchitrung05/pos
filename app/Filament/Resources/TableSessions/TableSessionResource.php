<?php

namespace App\Filament\Resources\TableSessions;

use App\Filament\Resources\Concerns\IsPosTransactionReadOnly;
use App\Filament\Resources\TableSessions\Pages\CreateTableSession;
use App\Filament\Resources\TableSessions\Pages\EditTableSession;
use App\Filament\Resources\TableSessions\Pages\ListTableSessions;
use App\Filament\Resources\TableSessions\Schemas\TableSessionForm;
use App\Filament\Resources\TableSessions\Tables\TableSessionsTable;
use App\Models\TableSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TableSessionResource extends Resource
{
    use IsPosTransactionReadOnly;

    /** Dùng store_id trực tiếp thay vì suy tenant qua bàn để ngăn truy vấn chéo chi nhánh. */
    protected static ?string $tenantRelationshipName = 'tableSessions';

    protected static ?string $model = TableSession::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Dining Tables';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    public static function form(Schema $schema): Schema
    {
        return TableSessionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TableSessionsTable::configure($table);
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
            'index' => ListTableSessions::route('/'),
            'create' => CreateTableSession::route('/create'),
            'edit' => EditTableSession::route('/{record}/edit'),
        ];
    }
}
