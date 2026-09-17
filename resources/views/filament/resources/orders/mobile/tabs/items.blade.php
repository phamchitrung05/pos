<section id="food" role="tabpanel" aria-labelledby="tab-food" tabindex="0" x-show="tab === 'items'" x-cloak class="space-y-3">
    @forelse ($orderItems as $item)
        @php
            $printedQuantity = (int) $item['kitchenPrintedQuantity'];
            $quantity = (int) $item['quantity'];
        @endphp

        <article class="rounded-2xl border border-slate-200 bg-white p-4">
            <div class="flex items-center gap-3">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-xl font-bold text-slate-500">
                    {{ mb_strtoupper(mb_substr($item['name'], 0, 1)) }}
                </span>
                <div class="min-w-0">
                    <h3 class="truncate text-[18px] font-bold">{{ $item['name'] }}</h3>
                    <p class="mt-1 text-sm text-slate-400">{{ $item['code'] }}</p>
                </div>

                @if ($printedQuantity >= $quantity)
                    <span class="ml-auto inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-emerald-50 px-2.5 py-2 text-xs font-semibold text-emerald-700">
                        <span class="size-1.5 rounded-full bg-emerald-500"></span>
                        Đã gửi
                    </span>
                @elseif ($printedQuantity > 0)
                    <span class="ml-auto inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-orange-50 px-2.5 py-2 text-xs font-semibold text-orange-600">
                        <span class="size-1.5 rounded-full bg-orange-500"></span>
                        Một phần
                    </span>
                @else
                    <span class="ml-auto inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-blue-50 px-2.5 py-2 text-xs font-semibold text-blue-600">
                        <span class="size-1.5 rounded-full bg-blue-500"></span>
                        Chờ gửi
                    </span>
                @endif
            </div>

            <dl class="mt-4 grid grid-cols-[1fr_0.75fr_1fr] divide-x divide-slate-200 border-t border-slate-200 pt-3">
                <div>
                    <dt class="text-[12px] text-slate-500">Đơn giá</dt>
                    <dd class="mt-1 text-[16px] font-bold tabular-nums">{{ $item['unitPriceLabel'] }}</dd>
                </div>
                <div class="text-center">
                    <dt class="text-[12px] text-slate-500">Số lượng</dt>
                    <dd class="mt-1 text-[16px] font-bold">{{ $quantity }}</dd>
                </div>
                <div class="text-right">
                    <dt class="text-[12px] text-slate-500">Thành tiền</dt>
                    <dd class="mt-1 text-[16px] font-bold tabular-nums">{{ $item['subtotalLabel'] }}</dd>
                </div>
            </dl>
        </article>
    @empty
        <div class="rounded-2xl border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500">
            Đơn hàng chưa có món.
        </div>
    @endforelse

    <section class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4" aria-labelledby="note-title">
        <x-heroicon-o-pencil-square class="mt-1 size-6 shrink-0 text-slate-500" />
        <div>
            <h3 id="note-title" class="font-semibold text-slate-500">Ghi chú</h3>
            <p class="mt-1 text-sm leading-6 text-slate-400">{{ $order['notes'] ?? 'Chưa có ghi chú cho đơn hàng.' }}</p>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 p-4" aria-labelledby="payment-title">
        <div class="flex items-center gap-2">
            <x-heroicon-o-banknotes class="size-5 shrink-0 text-slate-500" />
            <h3 id="payment-title" class="text-[16px] font-bold">Chi tiết thanh toán</h3>
            <span class="ml-auto text-sm text-slate-500">{{ $orderItemCount }} món</span>
        </div>
        <dl class="mt-4 space-y-3">
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Tạm tính</dt><dd class="tabular-nums">{{ $orderTotalLabel }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Giảm giá</dt><dd class="tabular-nums">0 đ</dd></div>
            <div class="flex items-center justify-between gap-2 border-t border-slate-200 pt-3"><dt class="text-[20px] font-bold">Tổng cộng</dt><dd class="text-[26px] font-bold tracking-tight text-orange-600 tabular-nums">{{ $orderTotalLabel }}</dd></div>
        </dl>
    </section>
</section>
