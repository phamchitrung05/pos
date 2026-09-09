<?php

namespace App\Filament\Resources\TableSessions\Pages;

use App\Filament\Resources\TableSessions\TableSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTableSessions extends ListRecords
{
    protected static string $resource = TableSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
