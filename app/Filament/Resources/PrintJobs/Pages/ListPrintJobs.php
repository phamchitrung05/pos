<?php

namespace App\Filament\Resources\PrintJobs\Pages;

use App\Filament\Resources\PrintJobs\PrintJobResource;
use Filament\Resources\Pages\ListRecords;

class ListPrintJobs extends ListRecords
{
    protected static string $resource = PrintJobResource::class;

    protected function getHeaderActions(): array
    {
        // PrintJob chỉ được sinh từ action nghiệp vụ, không cho tạo payload thủ công.
        return [];
    }
}
