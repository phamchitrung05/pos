@php
    $order = $record->order;
    $paidAmount = (float) ($order?->payments->sum('amount') ?? 0);
    $orderTotal = (float) ($order?->total ?? 0);
    $remainingAmount = max(0, $orderTotal - $paidAmount);
    $statusColor = match ($record->status) {
        \App\Enums\TableSessionStatus::Open => 'success',
        \App\Enums\TableSessionStatus::Closed => 'gray',
        \App\Enums\TableSessionStatus::Cancelled => 'danger',
    };
@endphp

<div id="view-moi" class="flex flex-col gap-6">
    <header class="flex flex-wrap items-center gap-4 border-b border-gray-200 pb-5 dark:border-white/10">
        <span class="flex size-14 shrink-0 items-center justify-center rounded-full bg-primary-500/10 text-primary-600">
            <x-heroicon-o-clock class="size-8" />
        </span>
        <div class="min-w-0">
            <h2 class="text-xl font-bold leading-7 text-gray-950 dark:text-white sm:text-2xl">
                Lịch sử phiên bàn
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $record->table?->name ?? 'Chưa xác định bàn' }}
                @if ($record->table?->zone?->name)
                    · {{ $record->table->zone->name }}
                @endif
            </p>
        </div>
        <x-filament::badge :color="$statusColor" class="ml-auto">
            {{ $record->status->getLabel() }}
        </x-filament::badge>
    </header>

    <dl class="grid grid-cols-2 gap-y-4 rounded-xl bg-primary-50 p-4 sm:grid-cols-4 sm:divide-x sm:divide-primary-100 sm:p-5 dark:bg-primary-950/30 dark:sm:divide-primary-900">
        <div class="px-2">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Ngày mở</dt>
            <dd class="mt-1 font-semibold text-gray-950 dark:text-white">
                {{ $record->start_time?->format('d/m/Y') ?? 'Chưa có' }}
            </dd>
        </div>
        <div class="px-2 sm:px-6">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Giờ vào</dt>
            <dd class="mt-1 font-semibold text-gray-950 dark:text-white">
                {{ $record->start_time?->format('H:i') ?? 'Chưa có' }}
            </dd>
        </div>
        <div class="px-2 sm:px-6">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Số món</dt>
            <dd class="mt-1 font-semibold text-gray-950 dark:text-white">
                {{ $order?->items->sum('quantity') ?? 0 }}
            </dd>
        </div>
        <div class="px-2 sm:px-6">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Nhân viên</dt>
            <dd class="mt-1 truncate font-semibold text-gray-950 dark:text-white">
                {{ $record->openedBy?->name ?? 'Hệ thống' }}
            </dd>
        </div>
    </dl>

    <dl class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 sm:p-5 dark:border-white/10 dark:bg-white/5">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Tiền món</dt>
            <dd class="mt-1 text-2xl font-bold tabular-nums text-gray-950 dark:text-white">
                {{ number_format($orderTotal, 0, ',', '.') }} đ
            </dd>
        </div>
        <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 sm:p-5 dark:border-white/10 dark:bg-white/5">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Đã thanh toán</dt>
            <dd class="mt-1 text-2xl font-bold tabular-nums text-gray-950 dark:text-white">
                {{ number_format($paidAmount, 0, ',', '.') }} đ
            </dd>
        </div>
        <div class="rounded-xl border border-red-500/15 bg-red-500/5 p-4 sm:p-5 dark:border-red-400/20 dark:bg-red-400/10">
            <dt class="text-sm text-red-600 dark:text-red-400">Còn phải thu</dt>
            <dd class="mt-1 text-2xl font-bold tabular-nums text-red-600 dark:text-red-400">
                {{ number_format($remainingAmount, 0, ',', '.') }} đ
            </dd>
        </div>
    </dl>

    <section aria-labelledby="session-log-heading">
        <div class="flex items-center justify-between gap-3 border-b border-gray-200 dark:border-white/10">
            <h3 id="session-log-heading" class="min-h-14 border-b-[3px] border-primary-500 px-3 pt-4 text-sm font-semibold text-primary-600 sm:text-base">
                Nhật ký thao tác
            </h3>
            <span class="pb-3 text-sm text-gray-500 dark:text-gray-400">
                {{ $events->count() }} thao tác
            </span>
        </div>

        <div class="mt-4 max-h-[min(28rem,50vh)] overflow-auto rounded-xl border border-gray-200 dark:border-white/10">
            <table class="w-full min-w-[640px] border-collapse text-left text-sm">
                <thead class="sticky top-0 z-10 bg-primary-50 text-gray-600 dark:bg-primary-950/30 dark:text-gray-300">
                    <tr>
                        <th class="w-[18%] px-4 py-4 font-semibold">Thời gian</th>
                        <th class="w-[24%] px-4 py-4 font-semibold">Thao tác</th>
                        <th class="px-4 py-4 font-semibold">Nội dung thay đổi</th>
                        <th class="w-[20%] px-4 py-4 font-semibold">Nhân viên</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($events as $event)
                        <tr class="hover:bg-primary-50/50 dark:hover:bg-primary-950/20">
                            <td class="whitespace-nowrap px-4 py-5 align-top tabular-nums text-gray-500 dark:text-gray-400">
                                {{ $event['at']?->format('d/m/Y H:i') ?? 'Không xác định' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-5 align-top font-medium text-gray-950 dark:text-white">
                                <span class="flex items-center gap-3">
                                    <x-heroicon-o-arrow-path class="size-5 shrink-0 text-primary-600" />
                                    {{ $event['title'] }}
                                </span>
                            </td>
                            <td class="px-4 py-5 align-top leading-6 text-gray-600 dark:text-gray-300">
                                {{ $event['description'] }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-5 align-top text-gray-600 dark:text-gray-300">
                                {{ $event['user'] ?? 'Hệ thống' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                Chưa có thao tác nào được ghi nhận.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
