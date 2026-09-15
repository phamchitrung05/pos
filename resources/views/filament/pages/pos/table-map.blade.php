<x-filament-panels::page full-height>
    @php
        // Page cha chỉ chuẩn bị dữ liệu cho modal; grid có read model và state riêng.
        $selectedTable = $tableMap['selectedTable'];
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
            'class' => 'w-full order-view-modal-window s950 scroll-none',
        ])"
    >
        @include('filament.pages.pos.table-modal')
    </x-filament::modal>
</x-filament-panels::page>
