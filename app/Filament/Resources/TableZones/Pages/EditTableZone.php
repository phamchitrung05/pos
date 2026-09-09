<?php

namespace App\Filament\Resources\TableZones\Pages;

use App\Filament\Resources\TableZones\TableZoneResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTableZone extends EditRecord
{
    protected static string $resource = TableZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
