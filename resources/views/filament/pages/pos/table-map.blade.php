<x-filament-panels::page full-height>
    @php
        // Page cha chỉ chuẩn bị dữ liệu cho modal; grid có read model và state riêng.
        $selectedTable = $tableMap['selectedTable'];
        $catalogProducts = collect($tableMap['catalog'])->flatMap(fn (array $group) => $group['products'])->keyBy('id');
        $draftTotal = collect($draftItems)->sum(fn (array $item) => ($catalogProducts[(int) $item['product_id']]['price'] ?? 0) * (int) $item['quantity']);
    @endphp

    <div class="fi-section-content flex h-full min-h-0 flex-col overflow-hidden bg-white text-slate-900">
        <main class="flex min-h-0 min-w-0 flex-1 flex-col">
            {{-- Header được giữ theo giao diện mới của trang. --}}
            <header class="flex h-[74px] shrink-0 items-center justify-between border-b border-slate-200 bg-white px-5 lg:px-7">
                <div class="flex items-center gap-6">
                    <h1 class="text-[24px] font-bold tracking-tight text-slate-950">Quản lý bàn</h1>
                </div>

                <div class="flex items-center gap-3">
                    <div class="hidden h-12 items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 md:flex">
                        <svg class="size-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <rect x="3" y="5" width="18" height="16" rx="2" stroke-width="1.8" />
                            <path stroke-width="1.8" d="M16 3v4M8 3v4M3 10h18" />
                        </svg>
                        <div>
                            <div class="text-xs text-slate-500">{{ now()->format('d/m/Y') }}</div>
                            <div class="text-sm font-bold text-slate-900">{{ now()->format('H:i') }}</div>
                        </div>
                    </div>

                    <button type="button" class="relative flex size-12 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" />
                        </svg>
                        <span class="absolute right-2 top-2 size-2 rounded-full bg-red-500 ring-2 ring-white"></span>
                    </button>
                </div>
            </header>

            {{-- TableGrid là boundary realtime, không làm morph modal hoặc state nhập liệu. --}}
            <div class="min-h-0 flex-1 p-4 lg:p-6">
                <livewire:pos.table-grid :store-id="$tableMap['storeId']" :selected-table-id="$selectedTableId" />
            </div>
        </main>
    </div>

    {{-- Modal luôn tồn tại trong DOM; nội dung được tách file để tiếp tục thiết kế độc lập. --}}
    <x-filament::modal id="table-details" width="7xl" teleport="body">
        @include('filament.pages.pos.table-modal')
    </x-filament::modal>
</x-filament-panels::page>
