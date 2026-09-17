<div class="hidden lg:block">
    @include('filament.pages.pos.table-modal', [
        'selectedTable' => $selectedTable,
        'events' => $events,
        'tableMap' => ['kitchenPrinters' => []],
        'isReadOnly' => true,
    ])
</div>

<div class="lg:hidden">
    @include('filament.resources.orders.view-modal-mobile', [
        'selectedTable' => $selectedTable,
        'events' => $events,
        'isReadOnly' => true,
    ])
</div>
