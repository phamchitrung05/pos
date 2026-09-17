<section id="info" role="tabpanel" aria-labelledby="tab-info" tabindex="0" x-show="tab === 'info'" x-cloak class="space-y-3">
    <dl class="divide-y divide-slate-200 rounded-2xl border border-slate-200 px-4">
        <div class="flex items-center justify-between gap-4 py-4">
            <dt class="text-sm text-slate-500">Tên bàn</dt>
            <dd class="text-right font-semibold">{{ $selectedTable['name'] }}</dd>
        </div>
        <div class="flex items-center justify-between gap-4 py-4">
            <dt class="text-sm text-slate-500">Khu vực</dt>
            <dd class="text-right font-semibold">{{ $selectedTable['zone']['name'] }}</dd>
        </div>
        <div class="flex items-center justify-between gap-4 py-4">
            <dt class="text-sm text-slate-500">Mã đơn</dt>
            <dd class="break-all text-right font-semibold">{{ $order['code'] ?? 'Chưa có' }}</dd>
        </div>
        <div class="flex items-center justify-between gap-4 py-4">
            <dt class="text-sm text-slate-500">Bắt đầu sử dụng</dt>
            <dd class="text-right font-semibold">{{ $session['startTimeLabel'] ?? 'Chưa mở bàn' }}</dd>
        </div>
        <div class="flex items-center justify-between gap-4 py-4">
            <dt class="text-sm text-slate-500">Trạng thái</dt>
            <dd class="text-right font-semibold">{{ $tableStatusLabel }}</dd>
        </div>
    </dl>

    <div class="rounded-2xl border border-slate-200 p-4">
        <h3 class="text-sm font-medium text-slate-500">Ghi chú</h3>
        <p class="mt-2 text-sm leading-6 text-slate-700">{{ $order['notes'] ?? 'Chưa có ghi chú.' }}</p>
    </div>
</section>
