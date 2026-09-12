@php
    $isOccupied = ($selectedTable['status'] ?? null) === 'occupied';
    $order = $selectedTable['order'] ?? null;
    $orderItems = $order['items'] ?? [];
    $orderItemCount = (int) ($order['itemCount'] ?? 0);
    $orderTotalLabel = $order['totalLabel'] ?? '0 đ';
    $session = $selectedTable['session'] ?? null;
    $draftLines = collect($draftItems)->map(function (array $draftItem) use ($catalogProducts): ?array {
        $product = $catalogProducts->get((int) $draftItem['product_id']);

        if (! $product) {
            return null;
        }

        return [
            ...$draftItem,
            'name' => $product['name'],
            'priceLabel' => $product['priceLabel'],
            'subtotalLabel' => number_format((float) $product['price'] * (int) $draftItem['quantity'], 0, ',', '.').' đ',
        ];
    })->filter()->values();
@endphp

<div
    wire:key="table-modal-{{ $selectedTable['id'] ?? 'empty' }}"
    x-data="{
        tab: 'orders',
        catalogOpen: false,
        loading: false,
        loadingTable: { name: '', zone: '' },
    }"
    x-on:table-modal-loading.window="
        loading = true;
        catalogOpen = false;
        tab = 'orders';
        loadingTable = $event.detail;
    "
    x-on:table-modal-loaded.window="loading = false"
    class="relative flex max-h-[85vh] min-h-[420px] flex-col overflow-hidden"
>
    {{-- Modal mở ngay tại client, lớp này che dữ liệu cũ trong lúc Page cha xác thực và nạp bàn mới. --}}
    <div
        x-show="loading"
        x-cloak
        class="absolute inset-0 z-50 flex items-center justify-center bg-white/95 backdrop-blur-sm"
    >
        <div class="text-center">
            <svg class="mx-auto size-10 animate-spin text-blue-600" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-20" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" />
                <path class="opacity-90" d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
            </svg>
            <div class="mt-4 text-base font-bold text-slate-900" x-text="loadingTable.name || 'Đang tải bàn'"></div>
            <div class="mt-1 text-sm text-slate-500" x-text="loadingTable.zone"></div>
        </div>
    </div>

    @if ($selectedTable)
        {{-- Header và các chỉ số tổng hợp của bàn đang chọn. --}}
        <div class="shrink-0 px-7 pt-6">
            <div class="flex items-start justify-between gap-4">
                <div class="flex min-w-0 items-center gap-4">
                    <div @class([
                        'flex size-16 shrink-0 items-center justify-center rounded-full',
                        'bg-emerald-50 text-emerald-600' => $isOccupied,
                        'bg-slate-100 text-slate-500' => ! $isOccupied,
                    ])>
                        <svg class="size-9" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 9h14v4H5V9Zm2 4v6m10-6v6M7 9V6h10v3" />
                        </svg>
                    </div>

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-3">
                            <h2 class="truncate text-[26px] font-bold text-slate-950">{{ $selectedTable['name'] }}</h2>
                            <span @class([
                                'inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-semibold ring-1',
                                'bg-emerald-50 text-emerald-700 ring-emerald-200' => $isOccupied,
                                'bg-slate-50 text-slate-600 ring-slate-200' => ! $isOccupied,
                            ])>
                                <span @class([
                                    'size-2 rounded-full',
                                    'bg-emerald-500' => $isOccupied,
                                    'bg-slate-400' => ! $isOccupied,
                                ])></span>
                                {{ $isOccupied ? 'Có khách' : 'Bàn trống' }}
                            </span>
                        </div>
                        <div class="mt-1 truncate text-[15px] font-medium text-slate-500">{{ $selectedTable['zone']['name'] }}</div>
                    </div>
                </div>

                <button
                    type="button"
                    wire:click="closeTableDetails"
                    x-on:click="$dispatch('close-modal', { id: 'table-details' })"
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                    aria-label="Đóng chi tiết bàn"
                >
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-width="1.8" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="flex min-h-[102px] items-center gap-4 rounded-xl border border-slate-200 bg-slate-50/70 px-5">
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <circle cx="12" cy="12" r="9" stroke-width="1.8" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 7v5l3 2" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm text-slate-500">Thời gian sử dụng</div>
                        <div class="mt-1 text-[20px] font-bold text-slate-950">{{ $session['elapsedFullLabel'] ?? '00:00:00' }}</div>
                        <div class="mt-0.5 text-sm text-slate-500">{{ $session ? 'Bắt đầu lúc: '.$session['startTimeLabel'] : 'Bàn chưa được mở' }}</div>
                    </div>
                </div>

                <div class="flex min-h-[102px] items-center gap-4 rounded-xl border border-slate-200 bg-slate-50/70 px-5">
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 3h12v18H6V3Zm3 5h6M9 12h6M9 16h4" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm text-slate-500">Số lượng món</div>
                        <div class="mt-1 text-[20px] font-bold text-slate-950">{{ $orderItemCount }} món</div>
                    </div>
                </div>

                <div class="flex min-h-[102px] items-center gap-4 rounded-xl border border-slate-200 bg-slate-50/70 px-5">
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 3h6l-1 4h-4L9 3Zm-2 5h10c2 2 4 5 4 8a5 5 0 0 1-5 5H8a5 5 0 0 1-5-5c0-3 2-6 4-8Z" />
                            <path stroke-linecap="round" stroke-width="1.8" d="M12 11v6" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm text-slate-500">Tổng tiền</div>
                        <div class="mt-1 text-[20px] font-bold text-slate-950">{{ $orderTotalLabel }}</div>
                    </div>
                </div>
            </div>

            @if ($isOccupied)
                <div class="mt-5 flex flex-col gap-3 border-b border-slate-200 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex items-center gap-6 overflow-x-auto">
                        @foreach (['orders' => 'Danh sách order ('.$orderItemCount.')', 'table' => 'Thông tin bàn', 'note' => 'Ghi chú'] as $tabKey => $tabLabel)
                            <button
                                type="button"
                                x-on:click="tab = '{{ $tabKey }}'"
                                x-bind:class="tab === '{{ $tabKey }}' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-800'"
                                class="whitespace-nowrap border-b-2 px-1 pb-4 pt-2 text-sm font-semibold transition"
                            >
                                {{ $tabLabel }}
                            </button>
                        @endforeach
                    </div>

                    <div class="flex items-center gap-2 pb-2">
                        <button
                            type="button"
                            x-on:click="catalogOpen = ! catalogOpen; tab = 'orders'"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-blue-200 bg-white px-4 text-sm font-semibold text-blue-600 transition hover:bg-blue-50"
                        >
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-width="1.8" d="M12 5v14M5 12h14" />
                            </svg>
                            Thêm món
                        </button>

                        <button
                            type="button"
                            wire:click="createKitchenTicket"
                            wire:loading.attr="disabled"
                            @disabled(! ($order['hasUnprintedItems'] ?? false) || empty($tableMap['kitchenPrinters']))
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition enabled:hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 9V4h12v5M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M7 14h10v7H7v-7Z" />
                            </svg>
                            Gửi bếp
                        </button>
                    </div>
                </div>
            @endif
        </div>

        <div class="flex-1 overflow-y-auto px-7 pb-5">
            @if ($isOccupied)
                {{-- Catalog chỉ xuất hiện khi thêm món, không chiếm chỗ trong luồng xem order thông thường. --}}
                <div x-show="catalogOpen" x-cloak x-transition class="mt-4 rounded-xl border border-blue-100 bg-blue-50/40 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="font-bold text-slate-900">Chọn món</h3>
                            <p class="mt-0.5 text-sm text-slate-500">Món được giữ trong giỏ tạm cho đến khi thêm vào order.</p>
                        </div>
                        @if (! empty($tableMap['kitchenPrinters']))
                            <select wire:model="selectedKitchenPrinterId" class="h-10 rounded-xl border-slate-200 bg-white text-sm text-slate-700">
                                <option value="">Máy in bếp mặc định</option>
                                @foreach ($tableMap['kitchenPrinters'] as $printer)
                                    <option value="{{ $printer['id'] }}">{{ $printer['name'] }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    <div class="mt-4 space-y-4">
                        @forelse ($tableMap['catalog'] as $group)
                            <div>
                                <div class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">{{ $group['name'] }}</div>
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach ($group['products'] as $product)
                                        <button
                                            type="button"
                                            wire:key="catalog-product-{{ $product['id'] }}"
                                            wire:click="addProductToDraft({{ $product['id'] }})"
                                            class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-3 text-left transition hover:border-blue-300 hover:shadow-sm"
                                        >
                                            <span class="min-w-0 pr-3">
                                                <span class="block truncate text-sm font-semibold text-slate-900">{{ $product['name'] }}</span>
                                                <span class="mt-0.5 block text-xs text-slate-500">{{ $product['priceLabel'] }}</span>
                                            </span>
                                            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">+</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <div class="py-6 text-center text-sm text-slate-500">Chưa có món đang bán.</div>
                        @endforelse
                    </div>

                    @if ($draftLines->isNotEmpty())
                        <div class="mt-4 rounded-xl border border-slate-200 bg-white p-4">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="text-sm font-bold text-slate-900">Giỏ tạm ({{ $draftLines->sum('quantity') }} món)</span>
                                <span class="text-sm font-bold text-blue-600">{{ number_format($draftTotal, 0, ',', '.') }} đ</span>
                            </div>
                            <div class="space-y-2">
                                @foreach ($draftLines as $draftLine)
                                    <div wire:key="draft-product-{{ $draftLine['product_id'] }}" class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2">
                                        <div class="min-w-0">
                                            <div class="truncate text-sm font-semibold text-slate-800">{{ $draftLine['name'] }}</div>
                                            <div class="text-xs text-slate-500">{{ $draftLine['subtotalLabel'] }}</div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button type="button" wire:click="changeDraftQuantity({{ $draftLine['product_id'] }}, -1)" class="flex size-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600">−</button>
                                            <span class="w-7 text-center text-sm font-bold text-slate-900">{{ $draftLine['quantity'] }}</span>
                                            <button type="button" wire:click="changeDraftQuantity({{ $draftLine['product_id'] }}, 1)" class="flex size-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600">+</button>
                                            <button type="button" wire:click="removeDraftItem({{ $draftLine['product_id'] }})" class="ml-1 text-xs font-semibold text-red-500">Xóa</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div x-show="tab === 'orders'" class="pt-4">
                    <div class="overflow-hidden rounded-xl border border-slate-200">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[900px]">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="w-14 px-4 py-3 text-left text-xs font-semibold text-slate-500">#</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">Món</th>
                                        <th class="w-36 px-4 py-3 text-left text-xs font-semibold text-slate-500">Đơn giá</th>
                                        <th class="w-28 px-4 py-3 text-center text-xs font-semibold text-slate-500">Số lượng</th>
                                        <th class="w-36 px-4 py-3 text-left text-xs font-semibold text-slate-500">Thành tiền</th>
                                        <th class="w-44 px-4 py-3 text-left text-xs font-semibold text-slate-500">Trạng thái</th>
                                        <th class="w-32 px-4 py-3 text-center text-xs font-semibold text-slate-500">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse ($orderItems as $index => $item)
                                        @php
                                            $printedQuantity = (int) $item['kitchenPrintedQuantity'];
                                            $minimumQuantity = max(1, $printedQuantity);
                                        @endphp
                                        <tr wire:key="order-item-{{ $item['id'] }}" class="transition hover:bg-slate-50/70">
                                            <td class="px-4 py-3 text-sm text-slate-700">{{ $index + 1 }}</td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-3">
                                                    <div class="flex size-11 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-sm font-bold text-slate-500">
                                                        {{ mb_strtoupper(mb_substr($item['name'], 0, 1)) }}
                                                    </div>
                                                    <div class="min-w-0">
                                                        <div class="truncate text-sm font-semibold text-slate-900">{{ $item['name'] }}</div>
                                                        <div class="mt-0.5 text-xs text-slate-400">{{ $item['code'] }}</div>
                                                        @if ($item['notes'])
                                                            <div class="mt-1 text-xs italic text-slate-500">{{ $item['notes'] }}</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-sm font-medium text-slate-700">{{ $item['unitPriceLabel'] }}</td>
                                            <td class="px-4 py-3 text-center text-sm font-semibold text-slate-800">{{ $item['quantity'] }}</td>
                                            <td class="px-4 py-3 text-sm font-semibold text-slate-900">{{ $item['subtotalLabel'] }}</td>
                                            <td class="px-4 py-3">
                                                @if ($printedQuantity >= (int) $item['quantity'])
                                                    <span class="inline-flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700"><span class="size-2 rounded-full bg-emerald-500"></span>Đã gửi bếp</span>
                                                @elseif ($printedQuantity > 0)
                                                    <span class="inline-flex items-center gap-2 rounded-lg bg-orange-50 px-3 py-1.5 text-xs font-semibold text-orange-600"><span class="size-2 rounded-full bg-orange-500"></span>Đã gửi một phần</span>
                                                @else
                                                    <span class="inline-flex items-center gap-2 rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-600"><span class="size-2 rounded-full bg-blue-500"></span>Chờ gửi bếp</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center justify-center gap-2">
                                                    <button
                                                        type="button"
                                                        wire:click="changeOrderItemQuantity({{ $item['id'] }}, {{ (int) $item['quantity'] - 1 }})"
                                                        wire:loading.attr="disabled"
                                                        @disabled((int) $item['quantity'] <= $minimumQuantity)
                                                        class="flex size-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition enabled:hover:border-blue-300 enabled:hover:bg-blue-50 enabled:hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-40"
                                                        aria-label="Giảm số lượng {{ $item['name'] }}"
                                                    >−</button>
                                                    <button
                                                        type="button"
                                                        wire:click="changeOrderItemQuantity({{ $item['id'] }}, {{ (int) $item['quantity'] + 1 }})"
                                                        wire:loading.attr="disabled"
                                                        class="flex size-9 items-center justify-center rounded-lg border border-blue-200 bg-white text-blue-600 transition hover:bg-blue-50"
                                                        aria-label="Tăng số lượng {{ $item['name'] }}"
                                                    >+</button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="px-6 py-12 text-center text-sm text-slate-500">Order chưa có món. Chọn “Thêm món” để bắt đầu.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[1.4fr_0.9fr]">
                        <div class="relative">
                            <svg class="absolute left-4 top-4 size-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 3h9l4 4v14H6V3Zm9 0v5h5M9 12h6M9 16h6" />
                            </svg>
                            <textarea rows="5" readonly placeholder="Chưa có ghi chú cho order." class="min-h-[145px] w-full resize-none rounded-xl border border-slate-200 bg-slate-50 py-4 pl-12 pr-4 text-sm text-slate-700 outline-none placeholder:text-slate-400">{{ $order['notes'] ?? '' }}</textarea>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-5">
                            <div class="space-y-3">
                                <div class="flex items-center justify-between text-sm"><span class="text-slate-600">Tạm tính</span><span class="font-medium text-slate-800">{{ $orderTotalLabel }}</span></div>
                                <div class="flex items-center justify-between text-sm"><span class="text-slate-600">Giảm giá</span><span class="font-medium text-slate-800">0 đ</span></div>
                                <div class="flex items-center justify-between text-sm"><span class="text-slate-600">Thuế</span><span class="font-medium text-slate-800">0 đ</span></div>
                            </div>
                            <div class="my-4 border-t border-slate-200"></div>
                            <div class="flex items-end justify-between gap-4">
                                <span class="text-[17px] font-bold text-slate-950">Tổng cộng</span>
                                <span class="text-[28px] font-bold leading-none text-blue-600">{{ $orderTotalLabel }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div x-show="tab === 'table'" x-cloak class="pt-4">
                    <div class="rounded-xl border border-slate-200 bg-white p-5">
                        <h3 class="text-[16px] font-bold text-slate-900">Thông tin bàn</h3>
                        <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div><div class="text-xs text-slate-500">Tên bàn</div><div class="mt-1 text-sm font-semibold text-slate-900">{{ $selectedTable['name'] }}</div></div>
                            <div><div class="text-xs text-slate-500">Khu vực</div><div class="mt-1 text-sm font-semibold text-slate-900">{{ $selectedTable['zone']['name'] }}</div></div>
                            <div><div class="text-xs text-slate-500">Sức chứa</div><div class="mt-1 text-sm font-semibold text-slate-900">Chưa cấu hình</div></div>
                            <div><div class="text-xs text-slate-500">Bắt đầu sử dụng</div><div class="mt-1 text-sm font-semibold text-slate-900">{{ $session['startTimeLabel'] ?? 'Chưa mở bàn' }}</div></div>
                            <div><div class="text-xs text-slate-500">Mã order</div><div class="mt-1 text-sm font-semibold text-slate-900">{{ $order['code'] ?? 'Chưa có' }}</div></div>
                        </div>
                    </div>
                </div>

                <div x-show="tab === 'note'" x-cloak class="pt-4">
                    <div class="rounded-xl border border-slate-200 bg-white p-5">
                        <label class="text-sm font-semibold text-slate-800">Ghi chú order</label>
                        <textarea rows="8" readonly placeholder="Chưa có ghi chú." class="mt-3 w-full resize-none rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 outline-none placeholder:text-slate-400">{{ $order['notes'] ?? '' }}</textarea>
                    </div>
                </div>
            @else
                <div class="flex min-h-[250px] flex-col items-center justify-center py-10 text-center">
                    <div class="flex size-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                        <svg class="size-9" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 9h14v4H5V9Zm2 4v6m10-6v6M7 9V6h10v3" />
                        </svg>
                    </div>
                    <h3 class="mt-4 text-lg font-bold text-slate-900">Bàn đang trống</h3>
                    <p class="mt-1 max-w-md text-sm text-slate-500">Mở bàn để tạo phiên phục vụ và bắt đầu thêm món vào order.</p>
                </div>
            @endif
        </div>

        <div class="flex shrink-0 flex-col-reverse gap-3 border-t border-slate-200 bg-white px-7 py-4 sm:flex-row sm:items-center sm:justify-end">
            <div class="flex items-center justify-end gap-3">
                <button
                    type="button"
                    wire:click="closeTableDetails"
                    x-on:click="$dispatch('close-modal', { id: 'table-details' })"
                    class="inline-flex h-11 min-w-[130px] items-center justify-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                >Đóng</button>

                @if (! $isOccupied)
                    <button type="button" wire:click="openTable" wire:loading.attr="disabled" class="inline-flex h-11 min-w-[160px] items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-50">
                        Mở bàn
                    </button>
                @elseif ($draftLines->isNotEmpty())
                    <button type="button" wire:click="addItems" wire:loading.attr="disabled" class="inline-flex h-11 min-w-[160px] items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-50">
                        Thêm vào order
                    </button>
                @elseif (($order['hasUnprintedItems'] ?? false) && ! empty($tableMap['kitchenPrinters']))
                    <button type="button" wire:click="createKitchenTicket" wire:loading.attr="disabled" class="inline-flex h-11 min-w-[160px] items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-50">
                        Gửi bếp
                    </button>
                @else
                    <button type="button" disabled class="inline-flex h-11 min-w-[160px] cursor-not-allowed items-center justify-center gap-2 rounded-xl bg-slate-200 px-5 text-sm font-semibold text-slate-500">
                        Đã lưu
                    </button>
                @endif
            </div>
        </div>
    @else
        {{-- Trạng thái ban đầu chỉ tồn tại trong DOM để Filament có thể mở modal tức thì. --}}
        <div class="flex flex-1 items-center justify-center">
            <div class="text-sm text-slate-500">Chọn một bàn để xem chi tiết.</div>
        </div>
    @endif
</div>
