<?php

namespace App\Filament\Resources\Printers;

use App\Filament\Resources\Printers\Pages\CreatePrinter;
use App\Filament\Resources\Printers\Pages\EditPrinter;
use App\Filament\Resources\Printers\Pages\ListPrinters;
use App\Filament\Resources\Printers\Schemas\PrinterForm;
use App\Filament\Resources\Printers\Tables\PrintersTable;
use App\Models\Printer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PrinterResource extends Resource
{
    /** Máy in chỉ được truy vấn và cấu hình trong Store đang được chọn. */
    protected static ?string $tenantRelationshipName = 'printers';

    protected static ?string $model = Printer::class;

    protected static ?string $modelLabel = 'Máy in';

    protected static ?string $pluralModelLabel = 'Máy in';

    protected static ?string $navigationLabel = 'Máy in';

    protected static string|\UnitEnum|null $navigationGroup = 'In ấn';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPrinter;

    public static function form(Schema $schema): Schema
    {
        return PrinterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PrintersTable::configure($table);
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
            'index' => ListPrinters::route('/'),
            'create' => CreatePrinter::route('/create'),
            'edit' => EditPrinter::route('/{record}/edit'),
        ];
    }
}
