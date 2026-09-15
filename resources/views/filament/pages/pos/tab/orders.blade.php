<div x-show="tab === 'orders'" x-cloak class="w-full min-w-0 max-w-full overflow-x-hidden">
    @if ($hasOrder)
        @if ($canManageOrder)
            <div class="mb-4 flex justify-end">
                <button
                    type="button"
                    x-on:click="$dispatch('open-modal', { id: 'add-product-modal' })"
                    class="flex h-10 items-center gap-2 rounded-lg bg-orange-500 px-5 text-sm font-semibold text-white transition hover:bg-orange-600"
                >
                    <svg class="size-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-width="1.8" d="M12 5v14M5 12h14" />
                    </svg>
                    Thêm món
                </button>
            </div>
        @endif

        <div class="w-full min-w-0 max-w-full overflow-hidden rounded-xl border border-slate-200">
            <div class="w-full min-w-0 max-w-full overflow-x-auto overscroll-x-contain">
                <table class="w-full min-w-[650px]">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="w-12 px-3 py-3 text-left text-xs font-semibold text-slate-500">#</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500">Tên món</th>
                            <th class="w-28 px-3 py-3 text-right text-xs font-semibold text-slate-500">Đơn giá</th>
                            <th class="w-20 px-3 py-3 text-center text-xs font-semibold text-slate-500">SL</th>
                            <th class="w-32 px-3 py-3 text-right text-xs font-semibold text-slate-500">Thành tiền</th>
                            <th class="w-36 px-3 py-3 text-center text-xs font-semibold text-slate-500">Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($orderItems as $index => $item)
                            @php
                                $printedQuantity = (int) $item['kitchenPrintedQuantity'];
                            @endphp

                            <tr wire:key="order-item-{{ $item['id'] }}" class="transition hover:bg-slate-50/70">
                                <td class="px-3 py-3 text-sm text-slate-700">{{ $index + 1 }}</td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-sm font-bold text-slate-500">
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
                                <td class="px-3 py-3 text-right text-sm font-medium text-slate-700">{{ $item['unitPriceLabel'] }}</td>
                                <td class="px-3 py-3 text-center text-sm font-semibold text-slate-800">{{ $item['quantity'] }}</td>
                                <td class="px-3 py-3 text-right text-sm font-semibold text-slate-900">{{ $item['subtotalLabel'] }}</td>
                                <td class="px-3 py-3 text-center">
                                    @if ($printedQuantity >= (int) $item['quantity'])
                                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-2 py-1.5 text-xs font-semibold text-emerald-700">
                                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                            Đã gửi
                                        </span>
                                    @elseif ($printedQuantity > 0)
                                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-orange-50 px-2 py-1.5 text-xs font-semibold text-orange-600">
                                            <span class="size-1.5 rounded-full bg-orange-500"></span>
                                            Một phần
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 px-2 py-1.5 text-xs font-semibold text-blue-600">
                                            <span class="size-1.5 rounded-full bg-blue-500"></span>
                                            Chờ gửi
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-sm text-slate-500">
                                    Đơn hàng chưa có món{{ $canManageOrder ? '. Chọn “Thêm món” để bắt đầu.' : '.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[1.3fr_0.9fr]">
            <textarea rows="4" readonly placeholder="Chưa có ghi chú cho đơn hàng." class="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 outline-none placeholder:text-slate-400">{{ $order['notes'] ?? '' }}</textarea>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-600">Tạm tính</span>
                    <span class="font-medium text-slate-800">{{ $orderTotalLabel }}</span>
                </div>
                <div class="mt-3 flex items-center justify-between text-sm">
                    <span class="text-slate-600">Giảm giá</span>
                    <span class="font-medium text-slate-800">0 đ</span>
                </div>
                <div class="my-3 border-t border-slate-200"></div>
                <div class="flex items-end justify-between gap-3">
                    <span class="text-[16px] font-bold text-slate-950">Tổng cộng</span>
                    <span class="text-[24px] font-bold leading-none text-orange-600">{{ $orderTotalLabel }}</span>
                </div>
            </div>
        </div>
    @else
        <div class="flex min-h-[350px] flex-col items-center justify-center text-center">
            <div class="flex size-16 items-center justify-center rounded-full bg-orange-50 text-orange-500">
                <svg class="size-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 9h14v4H5V9Zm2 4v6m10-6v6M7 9V6h10v3" />
                </svg>
            </div>
            <h3 class="mt-4 text-lg font-semibold text-slate-900">Bàn đang trống</h3>
            <p class="mt-1 text-sm text-slate-500">Mở bàn để tạo phiên phục vụ và bắt đầu thêm món vào đơn hàng.</p>
        </div>
    @endif
</div>
