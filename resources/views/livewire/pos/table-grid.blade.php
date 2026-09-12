@php
    // Các phép tính trình bày không làm thay đổi read model hoặc state Livewire.
    $statistics = $tableMap['statistics'];
    $emptyPercent = $statistics['total'] > 0 ? round($statistics['empty'] * 100 / $statistics['total']) : 0;
    $occupiedPercent = $statistics['total'] > 0 ? round($statistics['occupied'] * 100 / $statistics['total']) : 0;
@endphp

<div wire:poll.60s="refreshGrid" class="h-[80vh] flex min-h-0 flex-col overflow-hidden">
    {{-- =====================================================
    | THỐNG KÊ
    ====================================================== --}}
    <div class="grid shrink-0 grid-flow-col auto-cols-[220px] gap-3 overflow-x-auto pb-1 md:grid-flow-row md:grid-cols-3 md:overflow-visible md:pb-0">
        <div class="flex min-h-[112px] items-center gap-5 rounded-2xl border border-emerald-100 bg-emerald-50/60 px-5">
            <div class="flex size-16 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                <svg class="size-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 9h14v4H5V9Zm2 4v6m10-6v6M7 9V6h10v3" />
                </svg>
            </div>
            <div>
                <div class="text-[28px] font-bold leading-none text-slate-950">{{ $statistics['empty'] }}</div>
                <div class="mt-2 text-[15px] text-slate-600">Bàn trống</div>
                <div class="mt-0.5 text-[14px] font-semibold text-emerald-600">({{ $emptyPercent }}%)</div>
            </div>
        </div>

        <div class="flex min-h-[112px] items-center gap-5 rounded-2xl border border-emerald-100 bg-emerald-50 px-5">
            <div class="flex size-16 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                <svg class="size-9" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-1a3 3 0 1 0 0-6M22 21v-2a4 4 0 0 0-3-3.87" />
                </svg>
            </div>
            <div>
                <div class="text-[28px] font-bold leading-none text-slate-950">{{ $statistics['occupied'] }}</div>
                <div class="mt-2 text-[15px] text-slate-600">Có khách</div>
                <div class="mt-0.5 text-[14px] font-semibold text-emerald-600">({{ $occupiedPercent }}%)</div>
            </div>
        </div>

        <div class="flex min-h-[112px] items-center gap-5 rounded-2xl border border-slate-200 bg-white px-5">
            <div class="flex size-16 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                <svg class="size-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 9h14v4H5V9Zm2 4v6m10-6v6M7 9V6h10v3" />
                </svg>
            </div>
            <div>
                <div class="text-[28px] font-bold leading-none text-slate-950">{{ $statistics['total'] }}</div>
                <div class="mt-2 text-[15px] text-slate-600">Tổng số bàn</div>
            </div>
        </div>
    </div>

    {{-- =====================================================
    | BỘ LỌC KHU VỰC VÀ TÌM KIẾM
    ====================================================== --}}
    <div class="mt-5 flex shrink-0 flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <div class="flex items-center gap-2 overflow-x-auto pb-1 xl:flex-wrap xl:overflow-visible xl:pb-0">
            <button
                type="button"
                wire:click="$set('zoneFilter', 'all')"
                @class([
                    'h-10 shrink-0 rounded-xl border px-5 text-sm font-semibold transition',
                    'border-blue-600 bg-blue-600 text-white shadow-sm' => $zoneFilter === 'all',
                    'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' => $zoneFilter !== 'all',
                ])
            >
                Tất cả ({{ $statistics['total'] }})
            </button>

            @foreach ($tableMap['zones'] as $zone)
                <button
                    type="button"
                    wire:key="zone-filter-{{ $zone['id'] }}"
                    wire:click="$set('zoneFilter', '{{ $zone['id'] }}')"
                    @class([
                        'h-10 shrink-0 rounded-xl border px-5 text-sm font-semibold transition',
                        'border-blue-600 bg-blue-600 text-white shadow-sm' => $zoneFilter === (string) $zone['id'],
                        'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' => $zoneFilter !== (string) $zone['id'],
                    ])
                >
                    {{ $zone['name'] }} ({{ $zone['tableCount'] }})
                </button>
            @endforeach
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">

            <div class="flex items-center gap-5 whitespace-nowrap text-sm text-slate-500">
                <div class="flex items-center gap-2">
                    <span class="size-4 rounded-full border-2 border-slate-200 bg-white"></span>
                    Trống
                </div>
                <div class="flex items-center gap-2">
                    <span class="size-4 rounded-full bg-emerald-500 ring-4 ring-emerald-100"></span>
                    Có khách
                </div>
            </div>
        </div>
    </div>

    {{-- =====================================================
    | DANH SÁCH BÀN THEO KHU VỰC
    ====================================================== --}}
    <div class="mt-5 min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain pe-1 scroll-none" wire:loading.class="opacity-60">
        @forelse ($tableMap['groups'] as $group)
            <section wire:key="zone-{{ $group['id'] }}" class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div class="flex h-11 items-center border-b border-slate-200 bg-slate-50/80 px-4">
                    <h2 class="text-[16px] font-bold text-slate-900">
                        {{ $group['name'] }}
                        <span class="font-medium text-slate-500">({{ count($group['tables']) }} bàn)</span>
                    </h2>
                </div>

                <div class="grid grid-cols-2 gap-3 p-3 sm:grid-cols-4 xl:grid-cols-8">
                    @foreach ($group['tables'] as $table)
                        <button
                            type="button"
                            wire:key="table-{{ $table['id'] }}"
                            wire:click="$parent.selectTable({{ $table['id'] }})"
                            x-on:click="
                                $dispatch('table-modal-loading', {
                                    name: @js($table['name']),
                                    zone: @js($table['zone']['name']),
                                });
                                $dispatch('open-modal', { id: 'table-details' });
                            "
                            @class([
                                'group relative aspect-square min-h-[130px] overflow-hidden rounded-xl border transition-all duration-200',
                                'border-blue-500 ring-2 ring-blue-100' => $selectedTableId === $table['id'],
                                'border-emerald-200 bg-emerald-50 hover:border-emerald-300' => $table['status'] === 'occupied' && $selectedTableId !== $table['id'],
                                'border-slate-200 bg-white hover:border-blue-200 hover:bg-slate-50' => $table['status'] === 'empty' && $selectedTableId !== $table['id'],
                            ])
                        >
                            <div class="flex size-full flex-col items-center justify-center px-2 text-center">
                                @if ($table['status'] === 'occupied')
                                    <div class="mb-2 text-emerald-600">
                                        <svg class="size-9" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M8 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8.5-1a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM8 13c-4.42 0-8 2.24-8 5v2h12v-2c0-1.27.5-2.42 1.37-3.4C11.94 13.58 10.08 13 8 13Zm8.5-1c-1.44 0-2.78.35-3.87.95A6.6 6.6 0 0 1 15 18v2h9v-2c0-3.31-3.36-6-7.5-6Z" />
                                        </svg>
                                    </div>
                                @else
                                    <div class="mb-2 text-emerald-500">
                                        <svg class="size-9" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 9h14v4H5V9Zm2 4v6m10-6v6M7 9V6h10v3" />
                                        </svg>
                                    </div>
                                @endif

                                <div class="text-[17px] font-bold leading-none text-slate-950">{{ $table['name'] }}</div>

                                @if ($table['status'] === 'occupied')
                                    <div class="mt-2 text-[13px] font-medium text-slate-600">{{ $table['session']['elapsedLabel'] }}</div>
                                    <div class="mt-1 text-[13px] font-semibold text-slate-800">{{ $table['order']['totalLabel'] }}</div>
                                @else
                                    <div class="mt-2 text-[13px] font-medium text-slate-500">Trống</div>
                                @endif
                            </div>

                            {{-- Ring hover không nhận pointer event để click luôn đến button bàn. --}}
                            <div @class([
                                'pointer-events-none absolute inset-0 rounded-xl ring-inset transition',
                                'group-hover:ring-2 group-hover:ring-emerald-300' => $table['status'] === 'occupied',
                                'group-hover:ring-2 group-hover:ring-blue-200' => $table['status'] === 'empty',
                            ])></div>
                        </button>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center text-slate-500">
                Không tìm thấy bàn phù hợp với bộ lọc.
            </div>
        @endforelse
    </div>
</div>
