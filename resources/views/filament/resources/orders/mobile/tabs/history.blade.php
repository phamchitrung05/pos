<section id="history" role="tabpanel" aria-labelledby="tab-history" tabindex="0" x-show="tab === 'history'" x-cloak class="space-y-3">
    @forelse ($events as $event)
        <article class="rounded-2xl border border-slate-200 bg-white p-4">
            <div class="flex items-start justify-between gap-3">
                <h3 class="font-semibold text-slate-900">{{ $event['title'] }}</h3>
                <time class="shrink-0 text-xs text-slate-500" datetime="{{ $event['at']->toIso8601String() }}">
                    {{ $event['at']->format('H:i d/m/Y') }}
                </time>
            </div>
            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $event['description'] }}</p>
            <p class="mt-3 text-xs font-medium text-slate-500">{{ $event['user'] }}</p>
        </article>
    @empty
        <div class="rounded-2xl border border-dashed border-slate-300 px-4 py-10 text-center">
            <h3 class="font-semibold text-slate-900">Chưa có dữ liệu lịch sử</h3>
            <p class="mt-2 text-sm text-slate-500">Lịch sử hoạt động của bàn sẽ được hiển thị tại đây.</p>
        </div>
    @endforelse
</section>
