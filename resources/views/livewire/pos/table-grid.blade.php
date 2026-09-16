@php
    // Các phép tính trình bày không làm thay đổi read model hoặc state Livewire.
    $statistics = $tableMap['statistics'];
    $occupiedPercent = $statistics['total'] > 0 ? round($statistics['occupied'] * 100 / $statistics['total']) : 0;
    $emptyPercent = $statistics['total'] > 0 ? round($statistics['empty'] * 100 / $statistics['total']) : 0;
    $revenueLabel = number_format((float) ($statistics['revenue'] ?? 0), 0, ',', '.').' đ';
@endphp

<div wire:poll.60s="refreshGrid" class="h-[calc(100dvh-8rem)] bg-[#fbfaf8] text-[#101a33] overflow-auto lg:overflow-hidden scroll-none">
    <div class="flex min-h-full">
        <main class="min-w-0 flex-1">
            <div class="h-dvh">
                <header class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <h1 class="text-[32px] font-bold tracking-[-0.035em] text-slate-950">Sơ đồ bàn</h1>
                        <p class="mt-1 text-[15px] text-slate-500">Quản lý trạng thái bàn theo thời gian thực</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-4 pt-2 text-[14px] text-slate-500 sm:gap-6">
                        <div class="flex items-center gap-2 font-medium text-emerald-600"><span
                                class="size-2.5 rounded-full bg-emerald-500"></span>Đang hoạt động
                        </div>
                        <span class="hidden h-5 w-px bg-slate-200 sm:block"></span>
                        <div>{{ now()->translatedFormat('l, d/m/Y') }}</div>
                        <div>{{ now()->format('H:i') }}</div>
                        <button type="button"
                                class="relative flex size-10 items-center justify-center rounded-xl text-slate-700 hover:bg-white"
                                aria-label="Thông báo"><span class="text-xl">♧</span><span
                                class="absolute right-1.5 top-1.5 size-2 rounded-full bg-orange-500"></span></button>
                    </div>
                </header>

                <section class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div
                        class="flex min-h-[106px] items-center rounded-2xl border border-orange-100/60 bg-white px-5 shadow-[0_4px_18px_rgba(15,23,42,0.035)]">
                        <div
                            class="mr-5 flex size-14 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-2xl text-orange-500">
                            ▦
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Tổng bàn</div>
                            <div class="mt-1 flex items-end gap-2"><span
                                    class="text-[28px] font-bold leading-none text-slate-950">{{ $statistics['total'] }}</span><span
                                    class="pb-0.5 text-sm text-slate-500">bàn</span></div>
                        </div>
                    </div>
                    <div
                        class="flex min-h-[106px] items-center rounded-2xl border border-orange-100/60 bg-white px-5 shadow-[0_4px_18px_rgba(15,23,42,0.035)]">
                        <div
                            class="mr-5 flex size-14 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-2xl text-orange-500">
                            ♙
                        </div>
                        <div class="flex-1">
                            <div class="text-sm text-slate-500">Đang phục vụ</div>
                            <div class="mt-1 flex items-end gap-2"><span
                                    class="text-[28px] font-bold leading-none text-slate-950">{{ $statistics['occupied'] }}</span><span
                                    class="pb-0.5 text-sm text-slate-500">bàn</span></div>
                        </div>
                        <div class="relative size-14">
                            <svg viewBox="0 0 42 42" class="size-full -rotate-90">
                                <circle cx="21" cy="21" r="16" fill="none" stroke="#f1f1f1" stroke-width="4"/>
                                <circle cx="21" cy="21" r="16" fill="none" stroke="#ff6716" stroke-width="4"
                                        stroke-linecap="round" stroke-dasharray="{{ $occupiedPercent }} 100"/>
                            </svg>
                            <div
                                class="absolute inset-0 flex items-center justify-center text-xs font-semibold text-slate-600">{{ $occupiedPercent }}
                                %
                            </div>
                        </div>
                    </div>
                    <div
                        class="flex min-h-[106px] items-center rounded-2xl border border-orange-100/60 bg-white px-5 shadow-[0_4px_18px_rgba(15,23,42,0.035)]">
                        <div
                            class="mr-5 flex size-14 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-2xl text-orange-500">
                            ▦
                        </div>
                        <div class="flex-1">
                            <div class="text-sm text-slate-500">Bàn trống</div>
                            <div class="mt-1 flex items-end gap-2"><span
                                    class="text-[28px] font-bold leading-none text-slate-950">{{ $statistics['empty'] }}</span><span
                                    class="pb-0.5 text-sm text-slate-500">bàn</span></div>
                        </div>
                        <div class="relative size-14">
                            <svg viewBox="0 0 42 42" class="size-full -rotate-90">
                                <circle cx="21" cy="21" r="16" fill="none" stroke="#f1f1f1" stroke-width="4"/>
                                <circle cx="21" cy="21" r="16" fill="none" stroke="#ff6716" stroke-width="4"
                                        stroke-linecap="round" stroke-dasharray="{{ $emptyPercent }} 100"/>
                            </svg>
                            <div
                                class="absolute inset-0 flex items-center justify-center text-xs font-semibold text-slate-600">{{ $emptyPercent }}
                                %
                            </div>
                        </div>
                    </div>
                    <div
                        class="flex min-h-[106px] items-center rounded-2xl border border-orange-100/60 bg-white px-5 shadow-[0_4px_18px_rgba(15,23,42,0.035)]">
                        <div
                            class="mr-5 flex size-14 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-2xl text-orange-500">
                            ¢
                        </div>
                        <div class="flex-1">
                            <div class="text-sm text-slate-500">Doanh thu tạm tính</div>
                            <div
                                class="mt-1 text-[25px] font-bold leading-none text-slate-950">{{ $revenueLabel }}</div>
                        </div>
                        <div class="flex h-12 items-end gap-1 text-orange-300"><span
                                class="h-4 w-1.5 rounded-full bg-current"></span><span
                                class="h-7 w-1.5 rounded-full bg-current"></span><span
                                class="h-9 w-1.5 rounded-full bg-current"></span><span
                                class="h-12 w-1.5 rounded-full bg-current"></span></div>
                    </div>
                </section>

                <section
                    class="mb-4 flex flex-col gap-4 rounded-2xl border border-orange-100/60 bg-white p-4 shadow-[0_4px_18px_rgba(15,23,42,0.035)] xl:flex-row xl:items-center xl:justify-between">
                    <div class="flex flex-wrap gap-3">
                        <button type="button"
                                wire:click="$set('zoneFilter', 'all')" @class(['h-11 rounded-xl border px-6 text-sm font-medium transition', 'border-orange-500 bg-orange-500 text-white hover:bg-orange-500' => $zoneFilter === 'all', 'border-slate-200 bg-white text-slate-700 hover:border-orange-300 hover:bg-orange-50' => $zoneFilter !== 'all'])>
                            Tất cả ({{ $statistics['total'] }})
                        </button>
                        @foreach ($tableMap['zones'] as $zone)
                            <button type="button" wire:key="zone-filter-{{ $zone['id'] }}"
                                    wire:click="$set('zoneFilter', '{{ $zone['id'] }}')" @class(['h-11 rounded-xl border px-6 text-sm font-medium transition', 'border-orange-500 bg-orange-500 text-white hover:bg-orange-500' => $zoneFilter === (string) $zone['id'], 'border-slate-200 bg-white text-slate-700 hover:border-orange-300 hover:bg-orange-50' => $zoneFilter !== (string) $zone['id']])>{{ $zone['name'] }}
                                ({{ $zone['tableCount'] }})
                            </button>
                        @endforeach
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <button type="button" wire:click="refreshGrid" wire:loading.attr="disabled"
                                class="flex h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 px-5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-wait disabled:opacity-60">
                            <svg class="size-[18px]" wire:loading.class="animate-spin" fill="none" viewBox="0 0 24 24"
                                 stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                      d="M20 11a8 8 0 0 0-14.9-4M4 5v4h4M4 13a8 8 0 0 0 14.9 4M20 19v-4h-4"/>
                            </svg>
                            Làm mới
                        </button>
                    </div>
                </section>
                <div class="lg:h-[calc(100dvh-30rem)] h-[calc(100dvh-13rem)]  overflow-auto scroll-none">
                    <section wire:loading.class="opacity-60">
                        @forelse ($tableMap['groups'] as $group)
                            <section
                                class="mb-4 rounded-2xl border border-orange-100/60 bg-white px-4 py-5 shadow-[0_4px_20px_rgba(15,23,42,0.035)] sm:px-6">
                                <div wire:key="zone-group-{{ $group['id'] }}">
                                    <div class="mb-4 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="h-7 w-[6px] rounded-full bg-orange-500"></div>
                                            <h2 class="text-[22px] font-bold text-slate-950">{{ $group['name'] }}</h2>
                                        </div>
                                        <span class="text-sm text-slate-500">{{ count($group['tables']) }} bàn</span>
                                    </div>
                                    <div
                                        class="grid grid-cols-2 justify-items-center gap-3 sm:grid-cols-3 lg:grid-cols-6">
                                        @foreach ($group['tables'] as $table)
                                            @php($isOccupied = $table['status'] === 'occupied')
                                            <button type="button" wire:key="table-{{ $table['id'] }}"
                                                    wire:click="$parent.selectTable({{ $table['id'] }})"
                                                    x-on:click="$dispatch('table-modal-loading', { name: @js($table['name']), zone: @js($table['zone']['name']) }); $dispatch('open-modal', { id: 'table-details' });" @class(['group flex aspect-square w-full cursor-pointer flex-col items-center justify-center rounded-xl border text-center transition duration-200 hover:-translate-y-0.5 hover:shadow-md', 'border-orange-500 bg-gradient-to-br from-[#ff791f] to-[#ff6413] text-white shadow-sm hover:border-orange-500' => $isOccupied, 'border-slate-200 bg-white text-slate-950 hover:border-orange-200' => ! $isOccupied, 'ring-2 ring-orange-200' => $selectedTableId === $table['id']])>
                                                <div
                                                    class="text-[31px] font-bold leading-none tracking-[-0.03em]">{{ $table['name'] }}</div>
                                                <div @class(['my-2 h-px w-[70%]', 'bg-white/25' => $isOccupied, 'bg-slate-100' => ! $isOccupied])></div>@if ($isOccupied)
                                                    <div
                                                        class="text-[14px] font-medium">{{ $table['session']['elapsedLabel'] }}</div>
                                                    <div
                                                        class="mt-1 text-[17px] font-bold">{{ $table['order']['totalLabel'] }}</div>
                                                @else
                                                    <div class="text-[15px] text-slate-500">Trống</div>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </section>
                        @empty
                            <div
                                class="rounded-xl border border-dashed border-slate-300 px-6 py-14 text-center text-slate-500">
                                Không tìm thấy bàn phù hợp với bộ lọc.
                            </div>
                        @endforelse
                    </section>
                </div>
            </div>
        </main>
    </div>
</div>
