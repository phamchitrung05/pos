@php
    $selectedTable ??= null;
    $events ??= collect();
    $isOccupied = ($selectedTable['status'] ?? null) === 'occupied';
    $session = $selectedTable['session'] ?? null;
    $order = $selectedTable['order'] ?? null;
    $orderItems = $order['items'] ?? [];
    $orderItemCount = (int) ($order['itemCount'] ?? 0);
    $orderTotalLabel = $order['totalLabel'] ?? '0 đ';
    $tableStatusLabel = $session['statusLabel'] ?? ($isOccupied ? 'Đang có khách' : 'Đang trống');
    // Chế độ chỉ đọc (view order) dùng header/footer có sẵn của Filament Action.
    $isReadOnly = (bool) ($isReadOnly ?? false);
@endphp

<div
    wire:key="order-view-mobile-{{ $selectedTable['id'] ?? 'empty' }}"
    x-data="{
        tab: 'items',
        loading: false,
        loadingTable: { name: '', zone: '' },
    }"
    x-on:table-modal-loading.window="
        loading = true;
        tab = 'items';
        loadingTable = $event.detail;
    "
    x-on:table-modal-loaded.window="loading = false"
    data-order-view-mobile
    class="relative min-h-[20rem] table-map-modal-height text-gray-950 dark:text-white"
>
    <div x-show="loading" x-cloak class="absolute inset-0 z-50 flex items-center justify-center bg-white/95 backdrop-blur-sm">
        <div class="text-center">
            <x-filament::loading-indicator class="mx-auto size-10 text-orange-500" />
            <p class="mt-4 font-semibold text-slate-900" x-text="loadingTable.name || 'Đang tải bàn'"></p>
            <p class="mt-1 text-sm text-slate-500" x-text="loadingTable.zone"></p>
        </div>
    </div>

    @if ($selectedTable)
        <div class="flex h-full flex-col">
            @if (! $isReadOnly)
                @include('filament.resources.orders.mobile.header')
            @endif

            <div class="min-h-0 flex-1 overflow-y-auto table-map-modal-mt-4">
                @include('filament.resources.orders.mobile.table-summary')
                @include('filament.resources.orders.mobile.tabs.navigation')

                <main class="p-4">
                    @include('filament.resources.orders.mobile.tabs.items')
                    @include('filament.resources.orders.mobile.tabs.info')
                    @include('filament.resources.orders.mobile.tabs.history')
                </main>
            </div>

            @if (! $isReadOnly)
                @include('filament.resources.orders.mobile.footer')
            @endif
        </div>
    @endif
</div>
