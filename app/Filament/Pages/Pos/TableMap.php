<?php

namespace App\Filament\Pages\Pos;

use App\Actions\Pos\AddOrderItems;
use App\Actions\Pos\CreateKitchenPrintJob;
use App\Actions\Pos\OpenTableSession;
use App\Actions\Pos\UpdateOrderItem;
use App\Enums\PrinterType;
use App\Enums\TableSessionStatus;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Printer;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Store;
use App\Models\TableSession;
use App\Models\User;
use App\Queries\Pos\TableMapReadModel;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Màn hình vận hành POS tổng hợp theo Store hiện tại.
 *
 * Đây là standalone Filament Page vì một lượt phục vụ đi qua nhiều aggregate:
 * bàn, phiên bàn, order, dòng món, payment và lệnh in. Page chỉ điều phối dữ
 * liệu giao diện rồi gọi application action; mọi invariant vẫn nằm trong
 * `App\Actions\Pos` để API Tauri có thể tái sử dụng nguyên vẹn.
 */
class TableMap extends Page
{
    /** Giao diện sơ đồ bàn giữ vai trò adapter, mọi ghi dữ liệu đi qua application action. */
    protected string $view = 'filament.pages.pos.table-map';

    protected static ?string $title = 'Sơ đồ bàn';

    protected static ?string $navigationLabel = 'Sơ đồ bàn';

    protected static string|UnitEnum|null $navigationGroup = 'Vận hành POS';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = -100;

    /** Trang dùng header POS riêng để toàn bộ phần còn lại vừa đúng chiều cao viewport. */
    public function getHeading(): null
    {
        return null;
    }

    /** Không dựng breadcrumb Filament vì header POS đã thể hiện ngữ cảnh điều hướng. */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    /** ID bàn đang mở panel chi tiết; null nghĩa là chưa chọn bàn. */
    public ?int $selectedTableId = null;

    /**
     * Danh sách món đang chờ gửi từ giao diện.
     *
     * @var array<int, array{product_id: int, quantity: int, notes?: string|null}>
     */
    public array $draftItems = [];

    /** Máy in bếp được chọn; null sẽ dùng máy in bếp hoạt động đầu tiên của Store. */
    public ?int $selectedKitchenPrinterId = null;

    /**
     * Yêu cầu đủ quyền đọc mọi dữ liệu được tổng hợp trên trang.
     *
     * `strictAuthorization()` không tự bảo vệ các query viết tay, vì vậy Page
     * phải kiểm tra từng loại dữ liệu trước khi trả order, doanh thu, catalog
     * hoặc địa chỉ máy in cho trình duyệt.
     */
    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        foreach ([DiningTable::class, TableSession::class, Order::class, OrderItem::class, Payment::class, ProductGroup::class, Product::class, Printer::class] as $model) {
            if (! $user->can('viewAny', $model)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Cung cấp contract dữ liệu duy nhất cho Blade.
     *
     * Page cha chỉ nạp panel đang chọn và dữ liệu ít thay đổi. Tổng quan bàn
     * được giao cho TableGrid để WebSocket không morph giỏ món của Page.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'tableMap' => $this->buildTableMapData(app(TableMapReadModel::class)),
        ];
    }

    /**
     * Nạp riêng contract của Page cha, không query danh sách tất cả bàn.
     *
     * @return array<string, mixed>
     */
    private function buildTableMapData(TableMapReadModel $readModel): array
    {
        $store = $this->currentStore();

        return [
            'storeId' => (int) $store->getKey(),
            'selectedTable' => $readModel->selectedTable($store, $this->selectedTableId),
            'catalog' => $readModel->catalog($store),
            'kitchenPrinters' => $readModel->kitchenPrinters($store),
            'refreshedAt' => now()->toIso8601String(),
        ];
    }

    /** Đăng ký channel realtime theo Store; event bàn khác sẽ bị bỏ qua ở Page cha. */
    protected function getListeners(): array
    {
        $store = Filament::getTenant();

        if (! $store instanceof Store) {
            return [];
        }

        return [
            "echo-private:stores.{$store->getKey()}.pos,.pos.state.changed" => 'syncSelectedTableFromRealtime',
        ];
    }

    /** Chỉ refresh panel khi dữ liệu của chính bàn đang chọn đã thay đổi. */
    public function syncSelectedTableFromRealtime(array $event): void
    {
        $isSelectedTable = $this->selectedTableId !== null
            && (int) ($event['storeId'] ?? 0) === (int) $this->currentStore()->getKey()
            && (int) ($event['tableId'] ?? 0) === $this->selectedTableId;

        if (! $isSelectedTable) {
            $this->skipRender();
        }
    }

    /** Chọn một bàn để Blade hiển thị panel order hoặc thao tác mở bàn. */
    public function selectTable(int $tableId): void
    {
        $this->findTableInCurrentStore($tableId);

        if ($this->selectedTableId !== $tableId) {
            $this->draftItems = [];
        }

        $this->selectedTableId = $tableId;
        $this->resetValidation();

        // Client đã mở modal tức thì; các event này hoàn tất loading sau khi tenant được xác thực.
        $this->dispatch('open-modal', id: 'table-details');
        $this->dispatch('table-modal-loaded');
    }

    /** Thêm một sản phẩm vào giỏ tạm; giá bán luôn được action đọc lại từ database. */
    public function addProductToDraft(int $productId): void
    {
        $product = Product::query()
            ->where('store_id', $this->currentStore()->getKey())
            ->where('is_active', true)
            ->findOrFail($productId);
        $index = collect($this->draftItems)->search(
            fn (array $item): bool => (int) $item['product_id'] === (int) $product->getKey(),
        );

        if ($index === false) {
            $this->draftItems[] = [
                'product_id' => (int) $product->getKey(),
                'quantity' => 1,
                'notes' => null,
            ];

            return;
        }

        $this->draftItems[$index]['quantity'] = min(999, (int) $this->draftItems[$index]['quantity'] + 1);
    }

    /** Tăng hoặc giảm số lượng trong giỏ tạm, tự xóa dòng khi số lượng về không. */
    public function changeDraftQuantity(int $productId, int $delta): void
    {
        $index = collect($this->draftItems)->search(
            fn (array $item): bool => (int) $item['product_id'] === $productId,
        );

        if ($index === false) {
            return;
        }

        $quantity = (int) $this->draftItems[$index]['quantity'] + $delta;

        if ($quantity < 1) {
            $this->removeDraftItem($productId);

            return;
        }

        $this->draftItems[$index]['quantity'] = min(999, $quantity);
    }

    /** Xóa sản phẩm khỏi giỏ tạm và đánh lại index để Livewire hydrate ổn định. */
    public function removeDraftItem(int $productId): void
    {
        $this->draftItems = collect($this->draftItems)
            ->reject(fn (array $item): bool => (int) $item['product_id'] === $productId)
            ->values()
            ->all();
    }

    /** Đóng panel chi tiết mà không thay đổi session hoặc order trong database. */
    public function closeTableDetails(): void
    {
        $this->selectedTableId = null;
        $this->draftItems = [];
        $this->resetValidation();
        $this->dispatch('close-modal', id: 'table-details');
    }

    /**
     * Mở bàn được chỉ định hoặc bàn đang chọn.
     *
     * Nếu thiết bị khác đã mở bàn, OpenTableSession trả lại chính session/order
     * đang hoạt động; vì vậy người dùng có thể tiếp tục thao tác mà không gặp lỗi.
     */
    public function openTable(?int $tableId = null): void
    {
        $resolvedTableId = $this->resolveTableId($tableId);
        $table = $this->findTableInCurrentStore($resolvedTableId);
        $session = app(OpenTableSession::class)->handle($table, $this->currentUser());

        $this->selectedTableId = (int) $session->table_id;
        $this->resetValidation();

        Notification::make()
            ->title('Bàn đã sẵn sàng nhận món')
            ->success()
            ->send();
    }

    /**
     * Thêm danh sách món từ tham số hoặc `draftItems` vào order đang mở.
     *
     * @param  array<int, array{product_id: int, quantity: int, notes?: string|null}>|null  $items
     */
    public function addItems(?array $items = null): void
    {
        $itemsToAdd = $items ?? $this->draftItems;
        $order = $this->activeOrderForSelectedTable();

        app(AddOrderItems::class)->handle($order, $this->currentUser(), $itemsToAdd);

        $this->draftItems = [];
        $this->resetValidation();

        Notification::make()
            ->title('Đã cập nhật món và tổng tiền')
            ->success()
            ->send();
    }

    /** Cập nhật số lượng/ghi chú một dòng món thông qua invariant của UpdateOrderItem. */
    public function updateItem(int $orderItemId, int $quantity, ?string $notes = null): void
    {
        $order = $this->activeOrderForSelectedTable();

        // Scope đồng thời theo Store và order đang chọn để request Livewire giả
        // không thể sửa món của một bàn khác trong cùng chi nhánh.
        $orderItem = OrderItem::query()
            ->where('store_id', $this->currentStore()->getKey())
            ->where('order_id', $order->getKey())
            ->findOrFail($orderItemId);

        app(UpdateOrderItem::class)->handle($orderItem, $this->currentUser(), $quantity, $notes);
        $this->resetValidation();

        Notification::make()
            ->title('Đã cập nhật dòng món')
            ->success()
            ->send();
    }

    /** Đổi số lượng nhưng giữ nguyên ghi chú hiện tại, đặc biệt với món đã gửi bếp. */
    public function changeOrderItemQuantity(int $orderItemId, int $quantity): void
    {
        $orderItem = OrderItem::query()
            ->where('store_id', $this->currentStore()->getKey())
            ->where('order_id', $this->activeOrderForSelectedTable()->getKey())
            ->findOrFail($orderItemId);

        $this->updateItem($orderItemId, $quantity, $orderItem->notes);
    }

    /** Tạo snapshot phiếu bếp bằng máy được chọn hoặc máy bếp mặc định của Store. */
    public function createKitchenTicket(?int $printerId = null): void
    {
        $order = $this->activeOrderForSelectedTable();
        $printer = $this->findKitchenPrinter($printerId ?? $this->selectedKitchenPrinterId);

        $printJob = app(CreateKitchenPrintJob::class)
            ->handle($order, $printer, $this->currentUser());

        $this->selectedKitchenPrinterId = (int) $printer->getKey();
        $this->resetValidation();

        Notification::make()
            ->title("Đã tạo phiếu bếp #{$printJob->getKey()}")
            ->body('Thiết bị tại cửa hàng cần nhận PrintJob và gửi tới máy in LAN.')
            ->success()
            ->send();
    }

    /** Lấy bàn theo tenant thay vì tin table ID gửi từ Livewire frontend. */
    private function findTableInCurrentStore(int $tableId): DiningTable
    {
        return DiningTable::query()
            ->where('store_id', $this->currentStore()->getKey())
            ->findOrFail($tableId);
    }

    /** Lấy order của session open trên bàn đang chọn và báo lỗi nghiệp vụ dễ hiểu nếu không có. */
    private function activeOrderForSelectedTable(): Order
    {
        $tableId = $this->resolveTableId();

        $session = TableSession::query()
            ->where('store_id', $this->currentStore()->getKey())
            ->where('active_table_id', $tableId)
            ->where('status', TableSessionStatus::Open->value)
            ->with('order')
            ->first();

        if (! $session?->order) {
            throw ValidationException::withMessages([
                'selectedTableId' => 'Bàn chưa có phiên và order đang mở.',
            ]);
        }

        return $session->order;
    }

    /** Tìm máy in bếp trong Store hoặc tự lấy máy đầu tiên nếu giao diện chưa chọn. */
    private function findKitchenPrinter(?int $printerId): Printer
    {
        $query = Printer::query()
            ->where('store_id', $this->currentStore()->getKey())
            ->whereIn('printer_type', [PrinterType::Kitchen->value, PrinterType::Both->value])
            ->where('is_active', true);

        $printer = $printerId === null
            ? $query->orderBy('name')->first()
            : $query->find($printerId);

        if (! $printer) {
            throw ValidationException::withMessages([
                'selectedKitchenPrinterId' => 'Cửa hàng chưa có máy in bếp phù hợp.',
            ]);
        }

        return $printer;
    }

    /** Chuẩn hóa ID bàn từ tham số hoặc state hiện tại trước khi thực hiện action. */
    private function resolveTableId(?int $tableId = null): int
    {
        $resolvedTableId = $tableId ?? $this->selectedTableId;

        if ($resolvedTableId === null) {
            throw ValidationException::withMessages([
                'selectedTableId' => 'Vui lòng chọn một bàn trước khi thao tác.',
            ]);
        }

        return $resolvedTableId;
    }

    /** Lấy tenant Store đã được middleware Filament xác thực từ URL hiện tại. */
    private function currentStore(): Store
    {
        $store = Filament::getTenant();

        abort_unless($store instanceof Store, 404);

        return $store;
    }

    /** Lấy đúng model User đăng nhập để action tiếp tục kiểm tra Laravel Gate. */
    private function currentUser(): User
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
