<section aria-label="Thông tin bàn" class="rounded-2xl border border-orange-100 bg-cream p-2">
    <div class="flex items-center justify-between gap-3">
        <h2 class="text-[32px] leading-10 font-bold tracking-tight">{{ $selectedTable['name'] }}</h2>
        <span @class([
            'rounded-full px-4 py-2 text-sm font-semibold text-white',
            'bg-emerald-600' => $isOccupied,
            'bg-slate-500' => ! $isOccupied,
        ])>{{ $tableStatusLabel }}</span>
    </div>
    <p class="mt-2 flex items-center gap-2 font-semibold text-slate-500">
        <x-heroicon-o-map-pin class="size-5" />
        {{ $selectedTable['zone']['name'] }}
    </p>
    <div class="my-4 flex items-center gap-3 border-y border-slate-200 py-4">
        <x-heroicon-o-receipt-percent class="size-6 shrink-0 text-slate-500" />
        <div class="min-w-0">
            <p class="text-sm text-slate-500">Mã đơn</p>
            <p class="mt-0.5 break-words text-[17px] font-bold">{{ $order['code'] ?? 'Chưa có' }}</p>
        </div>
    </div>
    <dl class="grid grid-cols-2 divide-x divide-slate-200">
        <div class="flex items-start gap-2 pr-2">
            <x-heroicon-o-clock class="mt-2 size-6 shrink-0 text-slate-500" />
            <div>
                <dt class="text-[13px] text-slate-500">Thời gian vào</dt>
                <dd class="mt-1 text-[18px] font-bold">
                    <time datetime="{{ $session['startTime'] ?? '' }}">{{ $session['startClockLabel'] ?? '--:--' }}</time>
                </dd>
                <dd class="mt-0.5 text-[13px] text-slate-500">{{ $session['startDateLabel'] ?? 'Chưa mở bàn' }}</dd>
            </div>
        </div>
        <div class="flex items-start gap-2 pl-3">
            <x-heroicon-o-arrow-path class="mt-2 size-6 shrink-0 text-slate-500" />
            <div>
                <dt class="text-[13px] text-slate-500">Thời lượng</dt>
                <dd class="mt-1 text-[18px] font-bold tabular-nums">{{ $session['elapsedFullLabel'] ?? '00:00:00' }}</dd>
            </div>
        </div>
    </dl>
</section>
