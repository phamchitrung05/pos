<?php

namespace App\Filament\Resources\PrintJobs;

use App\Filament\Resources\Concerns\IsPosTransactionReadOnly;
use App\Filament\Resources\PrintJobs\Pages\ListPrintJobs;
use App\Filament\Resources\PrintJobs\Schemas\PrintJobForm;
use App\Filament\Resources\PrintJobs\Tables\PrintJobsTable;
use App\Models\PrintJob;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PrintJobResource extends Resource
{
    use IsPosTransactionReadOnly;

    /** Lệnh in được scope trực tiếp để không thể điều khiển thiết bị của Store khác. */
    protected static ?string $tenantRelationshipName = 'printJobs';

    protected static ?string $model = PrintJob::class;

    protected static ?string $modelLabel = 'Lệnh in';

    protected static ?string $pluralModelLabel = 'Lệnh in';

    protected static ?string $navigationLabel = 'Lệnh in';

    protected static string|\UnitEnum|null $navigationGroup = 'In ấn';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function form(Schema $schema): Schema
    {
        return PrintJobForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PrintJobsTable::configure($table);
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
            'index' => ListPrintJobs::route('/'),
        ];
    }
}
