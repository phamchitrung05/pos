<x-filament-panels::page full-height>
    @php
        // Page cha chỉ chuẩn bị dữ liệu cho modal; grid có read model và state riêng.
        $selectedTable = $tableMap['selectedTable'];
        $events = $tableMap['events'];
    @endphp

    <div class="fi-section-content flex h-full min-h-0 flex-col overflow-hidden bg-[#fbfaf8] text-slate-900">
        <div class="min-h-0 flex-1 overflow-y-auto">
            <livewire:pos.table-grid :store-id="$tableMap['storeId']" :selected-table-id="$selectedTableId" />
        </div>
    </div>

    {{-- Modal luôn tồn tại trong DOM; nội dung được tách file để tiếp tục thiết kế độc lập. --}}
    <x-filament::modal
        id="table-details"
        width="7xl"
        teleport="body"
        :extra-modal-window-attribute-bag="new \Filament\Support\View\ComponentAttributeBag([
            'class' => 'height-modal-custom table-map',
        ])"
    >
        <div class="hidden lg:block">
            @include('filament.pages.pos.table-modal')
        </div>

        <div class="lg:hidden">
            @include('filament.resources.orders.view-modal-mobile')
        </div>
    </x-filament::modal>
</x-filament-panels::page>
