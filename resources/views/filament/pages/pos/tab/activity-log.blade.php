<section aria-labelledby="session-log-heading">
    <div class="flex items-center justify-between gap-3 border-b border-gray-200 dark:border-white/10">
        <h3 id="session-log-heading" class="min-h-14 border-b-[3px] border-primary-500 px-3 pt-4 text-sm font-semibold text-primary-600 sm:text-base">
            Nhật ký thao tác
        </h3>
        <span class="pb-3 text-sm text-gray-500 dark:text-gray-400">
            {{ $events->count() }} thao tác
        </span>
    </div>

    <div class="mt-4 max-h-[min(28rem,50vh)] overflow-auto rounded-xl border border-gray-200 dark:border-white/10 scroll-none">
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
