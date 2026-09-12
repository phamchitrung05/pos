<?php

namespace App\Livewire\Pos;

use App\Models\Store;
use App\Queries\Pos\TableMapReadModel;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;
use Livewire\Component;

/** Component realtime chỉ quản lý thống kê, bộ lọc và grid bàn. */
final class TableGrid extends Component
{
    /** Store ID do Page truyền xuống và bị khóa để client không đổi tenant khi hydrate. */
    #[Locked]
    public int $storeId;

    /** ID bàn đang được Page cha chọn, chỉ dùng để tô trạng thái active. */
    #[Reactive]
    public ?int $selectedTableId = null;

    /** Các filter thuộc grid nên WebSocket refresh không đụng state giỏ món của Page cha. */
    public string $zoneFilter = 'all';

    public string $statusFilter = 'all';

    public string $search = '';

    /** Xác nhận Store prop đúng tenant Filament ngay khi component được mount. */
    public function mount(int $storeId, ?int $selectedTableId = null): void
    {
        $this->storeId = $storeId;
        abort_unless($this->currentStore()->getKey() === $storeId, 404);
        $this->selectedTableId = $selectedTableId;
    }

    /** Đăng ký private Echo channel động theo tenant của component. */
    protected function getListeners(): array
    {
        return [
            "echo-private:stores.{$this->storeId}.pos,.pos.state.changed" => 'handlePosStateChanged',
        ];
    }

    /** Event đã đúng channel Store nên chỉ cần render lại component grid nhỏ này. */
    public function handlePosStateChanged(array $event): void
    {
        abort_unless((int) ($event['storeId'] ?? 0) === $this->storeId, 403);
    }

    /** Fallback polling 60 giây chỉ chạy query/render grid khi WebSocket gián đoạn. */
    public function refreshGrid(): void
    {
        // Livewire tự render sau method, không cần giữ một bản sao read model trong state.
    }

    /** Dựng snapshot tổng quan bằng read model dùng chung và đúng Store đã khóa. */
    public function render(TableMapReadModel $readModel): View
    {
        return view('livewire.pos.table-grid', [
            'tableMap' => $readModel->overview(
                $this->currentStore(),
                $this->zoneFilter,
                $this->statusFilter,
                $this->search,
            ),
        ]);
    }

    /** Không dùng `Store::find($storeId)` làm tenant source vì prop vẫn đến từ browser. */
    private function currentStore(): Store
    {
        $store = Filament::getTenant();

        abort_unless($store instanceof Store && (int) $store->getKey() === $this->storeId, 404);

        return $store;
    }
}
