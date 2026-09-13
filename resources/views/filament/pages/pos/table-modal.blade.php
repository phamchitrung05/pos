@php
    $isOccupied = ($selectedTable['status'] ?? null) === 'occupied';
    $order = $selectedTable['order'] ?? null;
    $orderItems = $order['items'] ?? [];
    $orderItemCount = (int) ($order['itemCount'] ?? 0);
    $orderTotalLabel = $order['totalLabel'] ?? '0 đ';
    $session = $selectedTable['session'] ?? null;
@endphp

<div
    id="view-moi"
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
    class="relative flex max-h-[92vh] min-h-[420px] flex-col overflow-hidden rounded-2xl bg-white text-[#0f1f3d]"
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

        <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-4 lg:flex-row">
            <aside class="flex w-full shrink-0 flex-col gap-3 lg:w-[230px]">
                <div @class([
                    'flex h-[150px] flex-col items-center justify-center rounded-xl text-white',
                    'bg-gradient-to-br from-orange-500 to-orange-600' => $isOccupied,
                    'bg-slate-500' => ! $isOccupied,
                ])>
                    <span class="text-[15px] font-semibold">Bàn</span>
                    <div class="mt-1 text-[52px] font-bold leading-none tracking-[-0.05em]">{{ $selectedTable['name'] }}</div>
                    <span class="mt-3 text-[14px] font-medium">{{ $isOccupied ? 'Đang có khách' : 'Đang trống' }}</span>
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
                            <div class="text-xs text-slate-500">Mã order</div>
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

            <section class="flex min-h-[420px] min-w-0 flex-1 flex-col overflow-hidden rounded-xl border border-slate-200">
                <div class="flex shrink-0 overflow-x-auto border-b border-slate-200 bg-white">
                    @foreach (['orders' => 'Danh sách món', 'info' => 'Thông tin khác', 'history' => 'Lịch sử'] as $tabKey => $tabLabel)
                        <button
                            type="button"
                            x-on:click="tab = '{{ $tabKey }}'"
                            x-bind:class="tab === '{{ $tabKey }}' ? 'font-semibold text-orange-600 after:absolute after:bottom-0 after:left-3 after:right-3 after:h-[3px] after:rounded-full after:bg-orange-500' : 'text-slate-500 hover:text-slate-900'"
                            class="relative flex h-14 min-w-[130px] flex-1 items-center justify-center gap-2 whitespace-nowrap px-3 text-[13px] transition sm:text-[14px]"
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

                <div class="min-h-0 flex-1 overflow-y-auto bg-white p-4 sm:p-5">
                    <div x-show="tab === 'orders'" x-cloak>
                        @if ($isOccupied)
                            <div class="mb-4 flex justify-end">
                                <button
                                    type="button"
                                    x-on:click="$dispatch('open-modal', { id: 'add-product-modal' })"
                                    class="flex h-10 items-center gap-2 rounded-lg bg-orange-500 px-5 text-sm font-semibold text-white transition hover:bg-orange-600"
                                >
                                    <svg class="size-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.8" d="M12 5v14M5 12h14" /></svg>
                                    Thêm món
                                </button>
                            </div>

                            <div class="overflow-hidden rounded-xl border border-slate-200">
                                <div class="overflow-x-auto">
                                    <table class="w-full min-w-[650px]">
                                        <thead class="bg-slate-50"><tr><th class="w-12 px-3 py-3 text-left text-xs font-semibold text-slate-500">#</th><th class="px-3 py-3 text-left text-xs font-semibold text-slate-500">Tên món</th><th class="w-28 px-3 py-3 text-right text-xs font-semibold text-slate-500">Đơn giá</th><th class="w-20 px-3 py-3 text-center text-xs font-semibold text-slate-500">SL</th><th class="w-32 px-3 py-3 text-right text-xs font-semibold text-slate-500">Thành tiền</th><th class="w-36 px-3 py-3 text-center text-xs font-semibold text-slate-500">Trạng thái</th></tr></thead>
                                        <tbody class="divide-y divide-slate-100 bg-white">
                                            @forelse ($orderItems as $index => $item)
                                                @php
                                                    $printedQuantity = (int) $item['kitchenPrintedQuantity'];
                                                @endphp
                                                <tr wire:key="order-item-{{ $item['id'] }}" class="transition hover:bg-slate-50/70">
                                                    <td class="px-3 py-3 text-sm text-slate-700">{{ $index + 1 }}</td>
                                                    <td class="px-3 py-3"><div class="flex items-center gap-3"><div class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-sm font-bold text-slate-500">{{ mb_strtoupper(mb_substr($item['name'], 0, 1)) }}</div><div class="min-w-0"><div class="truncate text-sm font-semibold text-slate-900">{{ $item['name'] }}</div><div class="mt-0.5 text-xs text-slate-400">{{ $item['code'] }}</div>@if ($item['notes'])<div class="mt-1 text-xs italic text-slate-500">{{ $item['notes'] }}</div>@endif</div></div></td>
                                                    <td class="px-3 py-3 text-right text-sm font-medium text-slate-700">{{ $item['unitPriceLabel'] }}</td>
                                                    <td class="px-3 py-3 text-center text-sm font-semibold text-slate-800">{{ $item['quantity'] }}</td>
                                                    <td class="px-3 py-3 text-right text-sm font-semibold text-slate-900">{{ $item['subtotalLabel'] }}</td>
                                                    <td class="px-3 py-3 text-center">@if ($printedQuantity >= (int) $item['quantity'])<span class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-2 py-1.5 text-xs font-semibold text-emerald-700"><span class="size-1.5 rounded-full bg-emerald-500"></span>Đã gửi</span>@elseif ($printedQuantity > 0)<span class="inline-flex items-center gap-1.5 rounded-lg bg-orange-50 px-2 py-1.5 text-xs font-semibold text-orange-600"><span class="size-1.5 rounded-full bg-orange-500"></span>Một phần</span>@else<span class="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 px-2 py-1.5 text-xs font-semibold text-blue-600"><span class="size-1.5 rounded-full bg-blue-500"></span>Chờ gửi</span>@endif</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="6" class="px-6 py-12 text-center text-sm text-slate-500">Order chưa có món. Chọn “Thêm món” để bắt đầu.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[1.3fr_0.9fr]">
                                <textarea rows="4" readonly placeholder="Chưa có ghi chú cho order." class="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 outline-none placeholder:text-slate-400">{{ $order['notes'] ?? '' }}</textarea>
                                <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="flex items-center justify-between text-sm"><span class="text-slate-600">Tạm tính</span><span class="font-medium text-slate-800">{{ $orderTotalLabel }}</span></div><div class="mt-3 flex items-center justify-between text-sm"><span class="text-slate-600">Giảm giá</span><span class="font-medium text-slate-800">0 đ</span></div><div class="my-3 border-t border-slate-200"></div><div class="flex items-end justify-between gap-3"><span class="text-[16px] font-bold text-slate-950">Tổng cộng</span><span class="text-[24px] font-bold leading-none text-orange-600">{{ $orderTotalLabel }}</span></div></div>
                            </div>
                        @else
                            <div class="flex min-h-[350px] flex-col items-center justify-center text-center"><div class="flex size-16 items-center justify-center rounded-full bg-orange-50 text-orange-500"><svg class="size-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 9h14v4H5V9Zm2 4v6m10-6v6M7 9V6h10v3" /></svg></div><h3 class="mt-4 text-lg font-semibold text-slate-900">Bàn đang trống</h3><p class="mt-1 text-sm text-slate-500">Mở bàn để tạo phiên phục vụ và bắt đầu thêm món vào order.</p></div>
                        @endif
                    </div>

                    <div x-show="tab === 'info'" x-cloak class="pt-1"><div class="grid grid-cols-1 gap-3 sm:grid-cols-2"><div class="rounded-xl border border-slate-200 p-4"><div class="text-xs font-medium text-slate-500">Tên bàn</div><div class="mt-2 text-sm font-semibold text-slate-900">{{ $selectedTable['name'] }}</div></div><div class="rounded-xl border border-slate-200 p-4"><div class="text-xs font-medium text-slate-500">Khu vực</div><div class="mt-2 text-sm font-semibold text-slate-900">{{ $selectedTable['zone']['name'] }}</div></div><div class="rounded-xl border border-slate-200 p-4"><div class="text-xs font-medium text-slate-500">Mã order</div><div class="mt-2 text-sm font-semibold text-slate-900">{{ $order['code'] ?? 'Chưa có' }}</div></div><div class="rounded-xl border border-slate-200 p-4"><div class="text-xs font-medium text-slate-500">Bắt đầu sử dụng</div><div class="mt-2 text-sm font-semibold text-slate-900">{{ $session['startTimeLabel'] ?? 'Chưa mở bàn' }}</div></div></div><div class="mt-3 rounded-xl border border-slate-200 p-4"><div class="text-xs font-medium text-slate-500">Ghi chú</div><p class="mt-2 text-sm leading-6 text-slate-700">{{ $order['notes'] ?? 'Chưa có ghi chú.' }}</p></div></div>

                    <div x-show="tab === 'history'" x-cloak class="flex min-h-[350px] flex-col items-center justify-center text-center"><div class="flex size-16 items-center justify-center rounded-full bg-slate-100 text-slate-500"><svg class="size-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></div><h3 class="mt-4 text-lg font-semibold text-slate-900">Chưa có dữ liệu lịch sử</h3><p class="mt-1 text-sm text-slate-500">Lịch sử hoạt động của bàn sẽ được hiển thị tại đây.</p></div>
                </div>
            </section>
        </div>

        <footer class="flex shrink-0 flex-col-reverse gap-3 border-t border-slate-200 bg-white px-5 py-4 sm:flex-row sm:items-center sm:justify-end sm:px-6">
            <button type="button" wire:click="closeTableDetails" x-on:click="$dispatch('close-modal', { id: 'table-details' })" class="inline-flex h-11 min-w-[120px] items-center justify-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Đóng</button>

            @if (! $isOccupied)
                <button type="button" wire:click="openTable" wire:loading.attr="disabled" class="inline-flex h-11 min-w-[150px] items-center justify-center gap-2 rounded-xl bg-orange-500 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-orange-600 disabled:opacity-50">Mở bàn</button>
            @elseif (($order['hasUnprintedItems'] ?? false) && ! empty($tableMap['kitchenPrinters']))
                <button type="button" wire:click="createKitchenTicket" wire:loading.attr="disabled" class="inline-flex h-11 min-w-[150px] items-center justify-center gap-2 rounded-xl bg-orange-500 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-orange-600 disabled:opacity-50">Gửi bếp</button>
            @else
                <button type="button" disabled class="inline-flex h-11 min-w-[150px] cursor-not-allowed items-center justify-center gap-2 rounded-xl bg-slate-200 px-5 text-sm font-semibold text-slate-500">Đã lưu</button>
            @endif
        </footer>
    @else
        <div class="flex flex-1 items-center justify-center"><div class="text-sm text-slate-500">Chọn một bàn để xem chi tiết.</div></div>
    @endif
</div>

<x-filament::modal id="add-product-modal" width="7xl" teleport="body">
    <div class="min-h-[420px]"></div>
</x-filament::modal>
