@include('filament.pages.pos.table-modal', [
    'selectedTable' => $selectedTable,
    'events' => $events,
    'tableMap' => ['kitchenPrinters' => []],
    'isReadOnly' => true,
])
