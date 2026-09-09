<x-filament-panels::page>
    <div
        x-data="{
        area: 'all',
        search: '',
    }"
        class="min-h-screen bg-white text-slate-900"
    >
        <div class="flex min-h-screen">

            {{-- =========================================================
            | MAIN
            ========================================================== --}}
            <main class="min-w-0 flex-1">

                {{-- Top bar --}}
                <header
                    class="flex h-[74px] items-center justify-between
                       border-b border-slate-200 bg-white px-5 lg:px-7"
                >
                    <div class="flex items-center gap-6">

                        <h1 class="text-[24px] font-bold tracking-tight text-slate-950">
                            Quản lý bàn
                        </h1>

                        <div class="hidden items-center gap-2 text-sm md:flex">
                        <span class="font-medium text-blue-500">
                            POS
                        </span>

                            <span class="text-slate-300">/</span>

                            <span class="text-slate-400">
                            Quản lý bàn
                        </span>
                        </div>

                    </div>


                    <div class="flex items-center gap-3">

                        {{-- Date --}}
                        <div
                            class="hidden h-12 items-center gap-3 rounded-xl border
                               border-slate-200 bg-white px-4 md:flex"
                        >
                            <svg class="size-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <rect x="3" y="5" width="18" height="16" rx="2" stroke-width="1.8"/>
                                <path stroke-width="1.8" d="M16 3v4M8 3v4M3 10h18"/>
                            </svg>

                            <div>
                                <div class="text-xs text-slate-500">
                                    Thứ Sáu, 05/09/2026
                                </div>

                                <div class="text-sm font-bold text-slate-900">
                                    17:25
                                </div>
                            </div>
                        </div>


                        {{-- Bell --}}
                        <button
                            class="relative flex size-12 items-center justify-center rounded-xl
                               border border-slate-200 bg-white text-slate-600
                               transition hover:bg-slate-50"
                        >
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.8"
                                    d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"
                                />
                            </svg>

                            <span
                                class="absolute right-2 top-2 size-2 rounded-full
                                   bg-red-500 ring-2 ring-white"
                            ></span>
                        </button>

                    </div>
                </header>


                {{-- Main Content --}}
                <div class="p-4 lg:p-6">

                    {{-- =====================================================
                    | STATISTICS
                    ====================================================== --}}
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">

                        {{-- Empty --}}
                        <div
                            class="flex min-h-[112px] items-center gap-5 rounded-2xl
                               border border-emerald-100 bg-emerald-50/60 px-5"
                        >
                            <div
                                class="flex size-16 shrink-0 items-center justify-center
                                   rounded-full bg-emerald-100 text-emerald-600"
                            >
                                <svg class="size-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.8"
                                        d="M5 9h14v4H5V9Zm2 4v6m10-6v6M7 9V6h10v3"
                                    />
                                </svg>
                            </div>

                            <div>
                                <div class="text-[28px] font-bold leading-none text-slate-950">
                                    16
                                </div>

                                <div class="mt-2 text-[15px] text-slate-600">
                                    Bàn trống
                                </div>

                                <div class="mt-0.5 text-[14px] font-semibold text-emerald-600">
                                    (67%)
                                </div>
                            </div>
                        </div>


                        {{-- Occupied --}}
                        <div
                            class="flex min-h-[112px] items-center gap-5 rounded-2xl
                               border border-emerald-100 bg-emerald-50 px-5"
                        >
                            <div
                                class="flex size-16 shrink-0 items-center justify-center
                                   rounded-full bg-emerald-100 text-emerald-600"
                            >
                                <svg class="size-9" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.8"
                                        d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-1a3 3 0 1 0 0-6M22 21v-2a4 4 0 0 0-3-3.87"
                                    />
                                </svg>
                            </div>

                            <div>
                                <div class="text-[28px] font-bold leading-none text-slate-950">
                                    8
                                </div>

                                <div class="mt-2 text-[15px] text-slate-600">
                                    Có khách
                                </div>

                                <div class="mt-0.5 text-[14px] font-semibold text-emerald-600">
                                    (33%)
                                </div>
                            </div>
                        </div>


                        {{-- Total --}}
                        <div
                            class="flex min-h-[112px] items-center gap-5 rounded-2xl
                               border border-slate-200 bg-white px-5"
                        >
                            <div
                                class="flex size-16 shrink-0 items-center justify-center
                                   rounded-full bg-slate-100 text-slate-500"
                            >
                                <svg class="size-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.8"
                                        d="M5 9h14v4H5V9Zm2 4v6m10-6v6M7 9V6h10v3"
                                    />
                                </svg>
                            </div>

                            <div>
                                <div class="text-[28px] font-bold leading-none text-slate-950">
                                    24
                                </div>

                                <div class="mt-2 text-[15px] text-slate-600">
                                    Tổng số bàn
                                </div>
                            </div>
                        </div>

                    </div>


                    {{-- =====================================================
                    | FILTER
                    ====================================================== --}}
                    <div
                        class="mt-5 flex flex-col gap-4
                           xl:flex-row xl:items-center xl:justify-between"
                    >

                        {{-- Areas --}}
                        <div class="flex flex-wrap items-center gap-2">

                            <button
                                type="button"
                                @click="area = 'all'"
                                :class="
                                area === 'all'
                                    ? 'bg-blue-600 text-white border-blue-600 shadow-sm'
                                    : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'
                            "
                                class="h-10 rounded-xl border px-5 text-sm font-semibold transition"
                            >
                                Tất cả (24)
                            </button>

                            <button
                                type="button"
                                @click="area = 'a'"
                                :class="
                                area === 'a'
                                    ? 'bg-blue-600 text-white border-blue-600 shadow-sm'
                                    : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'
                            "
                                class="h-10 rounded-xl border px-5 text-sm font-semibold transition"
                            >
                                Khu A (8)
                            </button>

                            <button
                                type="button"
                                @click="area = 'b'"
                                :class="
                                area === 'b'
                                    ? 'bg-blue-600 text-white border-blue-600 shadow-sm'
                                    : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'
                            "
                                class="h-10 rounded-xl border px-5 text-sm font-semibold transition"
                            >
                                Khu B (8)
                            </button>

                            <button
                                type="button"
                                @click="area = 'c'"
                                :class="
                                area === 'c'
                                    ? 'bg-blue-600 text-white border-blue-600 shadow-sm'
                                    : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'
                            "
                                class="h-10 rounded-xl border px-5 text-sm font-semibold transition"
                            >
                                Khu C (8)
                            </button>

                        </div>


                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">

                            {{-- Search --}}
                            <div class="relative w-full sm:w-[290px]">
                                <svg
                                    class="absolute left-3.5 top-1/2 size-5
                                       -translate-y-1/2 text-slate-400"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <circle cx="11" cy="11" r="7" stroke-width="1.8"/>
                                    <path
                                        stroke-linecap="round"
                                        stroke-width="1.8"
                                        d="m20 20-4-4"
                                    />
                                </svg>

                                <input
                                    x-model="search"
                                    type="text"
                                    placeholder="Tìm bàn..."
                                    class="h-11 w-full rounded-xl border border-slate-200
                                       bg-white pl-11 pr-4 text-sm text-slate-700
                                       outline-none transition
                                       placeholder:text-slate-400
                                       focus:border-blue-400
                                       focus:ring-4 focus:ring-blue-50"
                                >
                            </div>


                            {{-- Legend --}}
                            <div class="flex items-center gap-5 whitespace-nowrap text-sm text-slate-500">

                                <div class="flex items-center gap-2">
                                <span
                                    class="size-4 rounded-full border-2 border-slate-200 bg-white"
                                ></span>

                                    Trống
                                </div>

                                <div class="flex items-center gap-2">
                                <span
                                    class="size-4 rounded-full bg-emerald-500
                                           ring-4 ring-emerald-100"
                                ></span>

                                    Có khách
                                </div>

                            </div>

                        </div>
                    </div>


                    @php
                        $areas = [
                            [
                                'id' => 'a',
                                'name' => 'Khu A - Tầng trệt',
                                'tables' => [
                                    ['name'=>'A1','occupied'=>false],
                                    ['name'=>'A2','occupied'=>true,'time'=>'00:45','amount'=>280000],
                                    ['name'=>'A3','occupied'=>false],
                                    ['name'=>'A4','occupied'=>true,'time'=>'01:20','amount'=>450000],
                                    ['name'=>'A5','occupied'=>true,'time'=>'00:30','amount'=>180000],
                                    ['name'=>'A6','occupied'=>false],
                                    ['name'=>'A7','occupied'=>true,'time'=>'01:10','amount'=>320000],
                                    ['name'=>'A8','occupied'=>false],
                                ]
                            ],
                            [
                                'id' => 'b',
                                'name' => 'Khu B - Tầng lầu',
                                'tables' => [
                                    ['name'=>'B1','occupied'=>true,'time'=>'00:20','amount'=>120000],
                                    ['name'=>'B2','occupied'=>false],
                                    ['name'=>'B3','occupied'=>true,'time'=>'00:55','amount'=>360000],
                                    ['name'=>'B4','occupied'=>false],
                                    ['name'=>'B5','occupied'=>false],
                                    ['name'=>'B6','occupied'=>true,'time'=>'00:40','amount'=>240000],
                                    ['name'=>'B7','occupied'=>false],
                                    ['name'=>'B8','occupied'=>true,'time'=>'01:35','amount'=>520000],
                                ]
                            ],
                            [
                                'id' => 'c',
                                'name' => 'Khu C - Sân vườn',
                                'tables' => [
                                    ['name'=>'C1','occupied'=>false],
                                    ['name'=>'C2','occupied'=>false],
                                    ['name'=>'C3','occupied'=>false],
                                    ['name'=>'C4','occupied'=>true,'time'=>'01:15','amount'=>410000],
                                    ['name'=>'C5','occupied'=>false],
                                    ['name'=>'C6','occupied'=>true,'time'=>'00:50','amount'=>300000],
                                    ['name'=>'C7','occupied'=>false],
                                    ['name'=>'C8','occupied'=>false],
                                ]
                            ],
                        ];
                    @endphp


                    {{-- =====================================================
                    | AREA
                    ====================================================== --}}
                    <div class="mt-5 space-y-4">

                        @foreach($areas as $areaItem)

                            <section
                                x-show="area === 'all' || area === '{{ $areaItem['id'] }}'"
                                x-transition.opacity
                                class="overflow-hidden rounded-2xl border border-slate-200 bg-white"
                            >

                                {{-- Area title --}}
                                <div
                                    class="flex h-11 items-center border-b border-slate-200
                                       bg-slate-50/80 px-4"
                                >
                                    <h2 class="text-[16px] font-bold text-slate-900">
                                        {{ $areaItem['name'] }}

                                        <span class="font-medium text-slate-500">
                                        ({{ count($areaItem['tables']) }} bàn)
                                    </span>
                                    </h2>
                                </div>


                                {{-- TABLE GRID --}}
                                <div
                                    class="grid grid-cols-2 gap-3 p-3
                                       sm:grid-cols-4
                                       xl:grid-cols-8"
                                >

                                    @foreach($areaItem['tables'] as $table)

                                        <button
                                            type="button"
                                            x-show="
                                            search === '' ||
                                            '{{ strtolower($table['name']) }}'.includes(
                                                search.toLowerCase()
                                            )
                                        "
                                            class="
                                            group relative aspect-square min-h-[130px]
                                            overflow-hidden rounded-xl border
                                            transition-all duration-200
                                            hover:-translate-y-0.5 hover:shadow-md

                                            {{ $table['occupied']
                                                ? 'border-emerald-200 bg-emerald-50 hover:border-emerald-300'
                                                : 'border-slate-200 bg-white hover:border-blue-200 hover:bg-slate-50'
                                            }}
                                        "
                                        >

                                            <div
                                                class="flex size-full flex-col items-center
                                                   justify-center px-2 text-center"
                                            >

                                                {{-- ICON --}}
                                                @if($table['occupied'])

                                                    {{-- Có khách --}}
                                                    <div class="mb-2 text-emerald-600">
                                                        <svg
                                                            class="size-9"
                                                            fill="currentColor"
                                                            viewBox="0 0 24 24"
                                                        >
                                                            <path
                                                                d="M8 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8.5-1a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM8 13c-4.42 0-8 2.24-8 5v2h12v-2c0-1.27.5-2.42 1.37-3.4C11.94 13.58 10.08 13 8 13Zm8.5-1c-1.44 0-2.78.35-3.87.95A6.6 6.6 0 0 1 15 18v2h9v-2c0-3.31-3.36-6-7.5-6Z"
                                                            />
                                                        </svg>
                                                    </div>

                                                @else

                                                    {{-- Bàn trống --}}
                                                    <div class="mb-2 text-emerald-500">
                                                        <svg
                                                            class="size-9"
                                                            fill="none"
                                                            viewBox="0 0 24 24"
                                                            stroke="currentColor"
                                                        >
                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                stroke-width="2.2"
                                                                d="M5 9h14v4H5V9Zm2 4v6m10-6v6M7 9V6h10v3"
                                                            />
                                                        </svg>
                                                    </div>

                                                @endif


                                                {{-- Table name --}}
                                                <div
                                                    class="text-[17px] font-bold leading-none text-slate-950"
                                                >
                                                    {{ $table['name'] }}
                                                </div>


                                                @if($table['occupied'])

                                                    {{-- Time --}}
                                                    <div class="mt-2 text-[13px] font-medium text-slate-600">
                                                        {{ $table['time'] }}
                                                    </div>

                                                    {{-- Amount --}}
                                                    <div
                                                        class="mt-1 text-[13px] font-semibold
                                                           text-slate-800"
                                                    >
                                                        {{ number_format($table['amount']) }} đ
                                                    </div>

                                                @else

                                                    <div
                                                        class="mt-2 text-[13px] font-medium text-slate-500"
                                                    >
                                                        Trống
                                                    </div>

                                                @endif

                                            </div>


                                            {{-- Hover effect --}}
                                            <div
                                                class="
                                                pointer-events-none absolute inset-0
                                                rounded-xl ring-inset transition

                                                {{ $table['occupied']
                                                    ? 'group-hover:ring-2 group-hover:ring-emerald-300'
                                                    : 'group-hover:ring-2 group-hover:ring-blue-200'
                                                }}
                                            "
                                            ></div>

                                        </button>

                                    @endforeach

                                </div>

                            </section>

                        @endforeach

                    </div>

                </div>
            </main>
        </div>
    </div>
</x-filament-panels::page>
