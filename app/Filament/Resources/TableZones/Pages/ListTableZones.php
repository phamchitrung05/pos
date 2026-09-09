<?php

namespace App\Filament\Resources\TableZones\Pages;

use App\Filament\Resources\TableZones\TableZoneResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTableZones extends ListRecords
{
    protected static string $resource = TableZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
