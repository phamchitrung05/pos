<div x-show="tab === 'history'" x-cloak>
    @isset($events)
        @include('filament.pages.pos.tab.activity-log')
    @else
        <div class="flex min-h-[350px] flex-col items-center justify-center text-center">
            <div class="flex size-16 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                <svg class="size-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <h3 class="mt-4 text-lg font-semibold text-slate-900">Chưa có dữ liệu lịch sử</h3>
            <p class="mt-1 text-sm text-slate-500">Lịch sử hoạt động của bàn sẽ được hiển thị tại đây.</p>
        </div>
    @endisset
</div>
