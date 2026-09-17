<nav class="sticky top-0 z-10 mt-3 border-b border-slate-200 bg-white" aria-label="Chi tiết bàn">
    <div role="tablist" aria-label="Nội dung bàn" class="tabs flex overflow-x-auto px-4 scroll-none">
        <button id="tab-food" type="button" role="tab" aria-controls="food" x-on:click="tab = 'items'" x-bind:aria-selected="tab === 'items'" x-bind:tabindex="tab === 'items' ? 0 : -1" class="flex min-h-14 shrink-0 items-center gap-2 px-3 text-sm font-semibold text-slate-500 aria-selected:text-orange-600">
            <x-heroicon-o-list-bullet class="size-5" />
            <span class="relative flex min-h-14 items-center">
                Danh sách món ({{ $orderItemCount }})
                <span x-show="tab === 'items'" x-cloak class="absolute inset-x-0 bottom-0 h-[3px] rounded-full bg-orange-500"></span>
            </span>
        </button>
        <button id="tab-info" type="button" role="tab" aria-controls="info" x-on:click="tab = 'info'" x-bind:aria-selected="tab === 'info'" x-bind:tabindex="tab === 'info' ? 0 : -1" class="flex min-h-14 shrink-0 items-center gap-2 px-3 text-sm font-medium text-slate-500 aria-selected:text-orange-600">
            <x-heroicon-o-information-circle class="size-5" />
            <span class="relative flex min-h-14 items-center">
                Thông tin khác
                <span x-show="tab === 'info'" x-cloak class="absolute inset-x-0 bottom-0 h-[3px] rounded-full bg-orange-500"></span>
            </span>
        </button>
        <button id="tab-history" type="button" role="tab" aria-controls="history" x-on:click="tab = 'history'" x-bind:aria-selected="tab === 'history'" x-bind:tabindex="tab === 'history' ? 0 : -1" class="flex min-h-14 shrink-0 items-center gap-2 px-3 text-sm font-medium text-slate-500 aria-selected:text-orange-600">
            <x-heroicon-o-clock class="size-5" />
            <span class="relative flex min-h-14 items-center">
                Lịch sử
                <span x-show="tab === 'history'" x-cloak class="absolute inset-x-0 bottom-0 h-[3px] rounded-full bg-orange-500"></span>
            </span>
        </button>
    </div>
</nav>
