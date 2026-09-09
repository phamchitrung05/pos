<?php

namespace App\Filament\Resources\PrintJobs\Pages;

use App\Filament\Resources\PrintJobs\PrintJobResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPrintJob extends EditRecord
{
    protected static string $resource = PrintJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
