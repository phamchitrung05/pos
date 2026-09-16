<div class="space-y-4 text-gray-950 dark:text-white">
    <section class="space-y-4 border-b border-gray-200 pb-5 dark:border-white/10">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm text-gray-500 dark:text-gray-400">Bàn phục vụ</p>
                <p class="truncate text-lg font-semibold text-gray-950 dark:text-white">
                    {{ $details['table_name'] }}
                    @if ($details['zone_name'])
                        <span class="font-normal text-gray-400">· {{ $details['zone_name'] }}</span>
                    @endif
                </p>
            </div>

            <x-filament::badge :color="$details['status_color']">
                {{ $details['status_label'] }}
            </x-filament::badge>
        </div>

        <dl class="grid grid-cols-2 gap-y-4 rounded-xl bg-primary-50 p-4 sm:grid-cols-4 sm:divide-x sm:divide-primary-100 sm:p-5 dark:bg-primary-950/30 dark:sm:divide-primary-900">
            <div class="px-2">
                <dt class="text-sm text-gray-500 dark:text-gray-400">Ngày mở</dt>
                <dd class="mt-1 font-semibold">{{ $details['opened_date'] }}</dd>
            </div>
            <div class="px-2 sm:px-6">
                <dt class="text-sm text-gray-500 dark:text-gray-400">Giờ vào</dt>
                <dd class="mt-1 font-semibold">{{ $details['opened_time'] }}</dd>
            </div>
            <div class="px-2 sm:px-6">
                <dt class="text-sm text-gray-500 dark:text-gray-400">Số món</dt>
                <dd class="mt-1 font-semibold">{{ $details['item_count'] }}</dd>
            </div>
            <div class="px-2 sm:px-6">
                <dt class="text-sm text-gray-500 dark:text-gray-400">Nhân viên</dt>
                <dd class="mt-1 truncate font-semibold">{{ $details['opened_by'] }}</dd>
            </div>
        </dl>

        <dl class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 sm:p-5 dark:border-white/10 dark:bg-white/5">
                <dt class="text-sm text-gray-500 dark:text-gray-400">Tiền món</dt>
                <dd class="mt-1 text-2xl font-bold tabular-nums">{{ $details['order_total'] }}</dd>
            </div>
            <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 sm:p-5 dark:border-white/10 dark:bg-white/5">
                <dt class="text-sm text-gray-500 dark:text-gray-400">Đã thanh toán</dt>
                <dd class="mt-1 text-2xl font-bold tabular-nums">{{ $details['paid_amount'] }}</dd>
            </div>
            <div class="rounded-xl border border-red-500/15 bg-red-500/5 p-4 sm:p-5 dark:border-red-400/20 dark:bg-red-400/10">
                <dt class="text-sm text-red-600 dark:text-red-400">Còn phải thu</dt>
                <dd class="mt-1 text-2xl font-bold tabular-nums text-red-600 dark:text-red-400">
                    {{ $details['remaining_amount'] }}
                </dd>
            </div>
        </dl>
    </section>

    <div class="pt-4">
        @include('filament.pages.pos.tab.activity-log', ['events' => $details['events']])
    </div>
</div>
