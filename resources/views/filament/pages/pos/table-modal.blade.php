@php
    $isOccupied = ($selectedTable['status'] ?? null) === 'occupied';
    $order = $selectedTable['order'] ?? null;
    $orderItems = $order['items'] ?? [];
    $orderItemCount = (int) ($order['itemCount'] ?? 0);
    $orderTotalLabel = $order['totalLabel'] ?? '0 đ';
    $session = $selectedTable['session'] ?? null;
    $hasOrder = $order !== null;
    $isReadOnly = (bool) ($isReadOnly ?? false);
    $canManageOrder = $isOccupied && ! $isReadOnly;
    $tableStatusLabel = $session['statusLabel'] ?? ($isOccupied ? 'Đang có khách' : 'Đang trống');
@endphp

<div
    wire:key="table-modal-{{ $selectedTable['id'] ?? 'empty' }}"
    x-data="{
        tab: 'orders',
        loading: false,
        loadingTable: { name: '', zone: '' },
    }"
    x-on:table-modal-loading.window="
        loading = true;
        tab = 'orders';
        loadingTable = $event.detail;
    "
    x-on:table-modal-loaded.window="loading = false"
    @class([
        'relative flex flex-col bg-white text-[#0f1f3d]',
        'max-h-[92dvh] min-h-[420px] overflow-hidden rounded-2xl' => ! $isReadOnly,
    ])
>
    <div x-show="loading" x-cloak class="absolute inset-0 z-50 flex items-center justify-center bg-white/95 backdrop-blur-sm">
        <div class="text-center">
            <svg class="mx-auto size-10 animate-spin text-orange-500" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-20" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" />
                <path class="opacity-90" d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
            </svg>
            <div class="mt-4 text-base font-bold text-slate-900" x-text="loadingTable.name || 'Đang tải bàn'"></div>
            <div class="mt-1 text-sm text-slate-500" x-text="loadingTable.zone"></div>
        </div>
    </div>

    @if ($selectedTable)
        @if (! $isReadOnly)
            <header class="flex h-[68px] shrink-0 items-center justify-between border-b border-slate-200 px-5 sm:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-600">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 9h14v4H5V9Zm2 4v6m10-6v6M7 9V6h10v3" />
                        </svg>
                    </div>
                    <h2 class="truncate text-[22px] font-bold tracking-[-0.02em] text-slate-950 sm:text-[25px]">
                        Chi tiết {{ $selectedTable['name'] }}
                    </h2>
                </div>

                <button
                    type="button"
                    wire:click="closeTableDetails"
                    x-on:click="$dispatch('close-modal', { id: 'table-details' })"
                    class="flex size-10 shrink-0 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                    aria-label="Đóng chi tiết bàn"
                >
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-width="1.8" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </header>
        @endif

        <div @class([
            'flex flex-col gap-4 p-4 lg:flex-row',
            'min-h-0 flex-1 overflow-y-auto' => ! $isReadOnly,
        ])>
            <aside class="flex w-full shrink-0 flex-col gap-3 lg:w-[230px]">
                <div @class([
                    'flex h-[150px] flex-col items-center justify-center rounded-xl text-white',
                    'bg-gradient-to-br from-orange-500 to-orange-600' => $isOccupied,
                    'bg-slate-500' => ! $isOccupied,
                ])>
                    <span class="text-[15px] font-semibold">Bàn</span>
                    <div class="mt-1 text-[52px] font-bold leading-none tracking-[-0.05em]">{{ $selectedTable['name'] }}</div>
                    <span class="mt-3 text-[14px] font-medium">{{ $tableStatusLabel }}</span>
                </div>

                <div class="grid grid-cols-2 gap-3 lg:grid-cols-1">
                    <div class="flex min-h-[82px] items-center gap-3 rounded-xl border border-slate-200 bg-white px-4">
                        <svg class="size-6 shrink-0 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <circle cx="12" cy="12" r="9" stroke-width="1.8" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 7v5l3 2" />
                        </svg>
                        <div class="min-w-0">
                            <div class="text-xs text-slate-500">Thời gian vào</div>
                            <div class="truncate text-[18px] font-bold leading-tight text-slate-950">{{ $session['startTimeLabel'] ?? 'Chưa mở bàn' }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $session['elapsedFullLabel'] ?? '00:00:00' }}</div>
                        </div>
                    </div>

                    <div class="flex min-h-[82px] items-center gap-3 rounded-xl border border-slate-200 bg-white px-4">
                        <svg class="size-6 shrink-0 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 3h12v18H6V3Zm3 5h6M9 12h6M9 16h4" />
                        </svg>
                        <div class="min-w-0">
                            <div class="text-xs text-slate-500">Tổng tiền</div>
                            <div class="truncate text-[20px] font-bold leading-tight text-slate-950">{{ $orderTotalLabel }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $orderItemCount }} món</div>
                        </div>
                    </div>

                    <div class="flex min-h-[82px] items-center gap-3 rounded-xl border border-slate-200 bg-white px-4">
                        <svg class="size-6 shrink-0 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5h16v14H4V5Zm4 4h8M8 13h5" />
                        </svg>
                        <div class="min-w-0">
                            <div class="text-xs text-slate-500">Mã đơn</div>
                            <div class="truncate text-[17px] font-bold text-slate-950">{{ $order['code'] ?? 'Chưa có' }}</div>
                        </div>
                    </div>

                    <div class="flex min-h-[82px] items-center gap-3 rounded-xl border border-slate-200 bg-white px-4">
                        <svg class="size-6 shrink-0 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11Z" />
                            <circle cx="12" cy="10" r="2.2" stroke-width="1.8" />
                        </svg>
                        <div class="min-w-0">
                            <div class="text-xs text-slate-500">Khu vực</div>
                            <div class="truncate text-[17px] font-bold text-slate-950">{{ $selectedTable['zone']['name'] }}</div>
                        </div>
                    </div>
                </div>
            </aside>

            <section @class([
                'flex min-h-0 min-w-0 flex-1 flex-col
                        overflow-hidden rounded-xl border border-slate-200',
                'overflow-hidden' => ! $isReadOnly,
            ])>
                <div class="flex shrink-0 overflow-x-auto border-b border-slate-200 scroll-none">
                    @foreach (['orders' => 'Danh sách món', 'info' => 'Thông tin khác', 'history' => 'Lịch sử'] as $tabKey => $tabLabel)
                        <button
                            type="button"
                            x-on:click="tab = '{{ $tabKey }}'"
                            x-bind:class="tab === '{{ $tabKey }}' ? 'font-semibold text-orange-600 after:absolute after:bottom-0 after:left-3 after:right-3 after:h-[3px] after:rounded-full after:bg-orange-500' : 'text-slate-500 hover:text-slate-900'"
                            class="relative flex h-14 flex-1 items-center justify-center gap-2 whitespace-nowrap px-3 text-[13px] transition sm:text-[14px]"
                        >
                            @if ($tabKey === 'orders')
                                <svg class="size-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.8" d="M6 3h12v18H6V3Zm3 5h6M9 12h6M9 16h4" /></svg>
                                {{ $tabLabel }} ({{ $orderItemCount }})
                            @elseif ($tabKey === 'info')
                                <svg class="size-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 3h12v18H6V3Zm3 5h6M9 12h6M9 16h4" /></svg>
                                {{ $tabLabel }}
                            @else
                                <svg class="size-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                {{ $tabLabel }}
                            @endif
                        </button>
                    @endforeach
                </div>

                <div @class([
                    'bg-white p-4 sm:p-5',
                    'min-h-0 flex-1 overflow-y-auto scroll-none' => ! $isReadOnly,
                ])>
                    @include('filament.pages.pos.tab.orders')
                    @include('filament.pages.pos.tab.info')
                    @include('filament.pages.pos.tab.history')
                </div>
            </section>
        </div>

    @else
        <div class="flex flex-1 items-center justify-center"><div class="text-sm text-slate-500">Chọn một bàn để xem chi tiết.</div></div>
    @endif
</div>

@if (! $isReadOnly)
    <x-filament::modal id="add-product-modal" width="7xl" teleport="body">
        <div class="min-h-[420px]"></div>
    </x-filament::modal>
@endif
