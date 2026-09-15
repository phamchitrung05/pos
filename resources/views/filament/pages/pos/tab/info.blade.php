<div x-show="tab === 'info'" x-cloak class="pt-1">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div class="rounded-xl border border-slate-200 p-4">
            <div class="text-xs font-medium text-slate-500">Tên bàn</div>
            <div class="mt-2 text-sm font-semibold text-slate-900">{{ $selectedTable['name'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 p-4">
            <div class="text-xs font-medium text-slate-500">Khu vực</div>
            <div class="mt-2 text-sm font-semibold text-slate-900">{{ $selectedTable['zone']['name'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 p-4">
            <div class="text-xs font-medium text-slate-500">Mã đơn</div>
            <div class="mt-2 text-sm font-semibold text-slate-900">{{ $order['code'] ?? 'Chưa có' }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 p-4">
            <div class="text-xs font-medium text-slate-500">Bắt đầu sử dụng</div>
            <div class="mt-2 text-sm font-semibold text-slate-900">{{ $session['startTimeLabel'] ?? 'Chưa mở bàn' }}</div>
        </div>
    </div>

    <div class="mt-3 rounded-xl border border-slate-200 p-4">
        <div class="text-xs font-medium text-slate-500">Ghi chú</div>
        <p class="mt-2 text-sm leading-6 text-slate-700">{{ $order['notes'] ?? 'Chưa có ghi chú.' }}</p>
    </div>
</div>
