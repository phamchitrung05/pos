<footer class="shrink-0 border-t border-slate-200 bg-white p-4">
    <button
        type="button"
        wire:click="closeTableDetails"
        x-on:click="$dispatch('close-modal', { id: 'table-details' })"
        class="flex h-12 w-full items-center justify-center rounded-xl bg-orange-500 text-base font-bold text-white transition hover:bg-orange-600"
    >
        Đóng
    </button>
</footer>
