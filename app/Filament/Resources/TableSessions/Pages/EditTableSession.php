<?php

namespace App\Filament\Resources\TableSessions\Pages;

use App\Filament\Resources\TableSessions\TableSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTableSession extends EditRecord
{
    protected static string $resource = TableSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
