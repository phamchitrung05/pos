<x-filament-panels::page full-height>
    @php
        // Page cha chỉ chuẩn bị dữ liệu cho modal; grid có read model và state riêng.
        $selectedTable = $tableMap['selectedTable'];
    @endphp

    <div class="fi-section-content flex h-full min-h-0 flex-col overflow-hidden bg-[#fbfaf8] text-slate-900">
        <div id="view-moi" class="min-h-0 flex-1 overflow-y-auto">
            <livewire:pos.table-grid :store-id="$tableMap['storeId']" :selected-table-id="$selectedTableId" />
        </div>
    </div>

    {{-- Modal luôn tồn tại trong DOM; nội dung được tách file để tiếp tục thiết kế độc lập. --}}
    <x-filament::modal id="table-details" width="7xl" teleport="body">
        @include('filament.pages.pos.table-modal')
    </x-filament::modal>
</x-filament-panels::page>
