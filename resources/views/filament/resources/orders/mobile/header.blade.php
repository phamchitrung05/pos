<header class="flex shrink-0 items-center justify-between border-b border-slate-200 bg-white px-4 py-2">
    <h2 class="truncate text-lg font-bold tracking-tight text-slate-950">Chi Tiết Bàn</h2>

    <button
        type="button"
        wire:click="closeTableDetails"
        x-on:click="$dispatch('close-modal', { id: 'table-details' })"
        class="flex size-10 shrink-0 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
        aria-label="Đóng chi tiết bàn"
    >
        <x-heroicon-o-x-mark class="size-6" />
    </button>
</header>
