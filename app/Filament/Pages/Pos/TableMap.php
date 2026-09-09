<?php

namespace App\Filament\Pages\Pos;

use App\Actions\Pos\AddOrderItems;
use App\Actions\Pos\CheckoutTable;
use App\Actions\Pos\CreateKitchenPrintJob;
use App\Actions\Pos\OpenTableSession;
use App\Actions\Pos\UpdateOrderItem;
use App\Enums\PaymentStatus;
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
use App\Models\TableZone;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
    /** View được cố ý để trống phần giao diện để người dùng tự triển khai thiết kế. */
    protected string $view = 'filament.pages.pos.table-map';

    protected static ?string $slug = 'pos/table-map';

    protected static ?string $title = 'Sơ đồ bàn';

    protected static ?string $navigationLabel = 'Sơ đồ bàn';

    protected static string|UnitEnum|null $navigationGroup = 'Vận hành POS';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = -100;

    /** ID bàn đang mở panel chi tiết; null nghĩa là chưa chọn bàn. */
    public ?int $selectedTableId = null;

    /** ID khu vực cần hiển thị hoặc chuỗi `all` để hiển thị toàn bộ. */
    public string $zoneFilter = 'all';

    /** Trạng thái lọc hỗ trợ `all`, `empty` và `occupied`. */
    public string $statusFilter = 'all';

    /** Từ khóa tìm theo tên bàn, được áp dụng phía server khi component render lại. */
    public string $search = '';

    /**
     * Danh sách món đang chờ gửi từ giao diện.
     *
     * @var array<int, array{product_id: int, quantity: int, notes?: string|null}>
     */
    public array $draftItems = [];

    /** Máy in bếp được chọn; null sẽ dùng máy in bếp hoạt động đầu tiên của Store. */
    public ?int $selectedKitchenPrinterId = null;

    /** UUID dùng lại trong các lần gọi checkout khi state Livewire hiện tại vẫn còn hiệu lực. */
    public ?string $checkoutRequestId = null;

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
     * Blade tương lai nhận biến `$tableMap` gồm `zones`, `tables`, `groups`,
     * `statistics`, `selectedTable`, `catalog`, `kitchenPrinters` và
     * `refreshedAt`. Polling chỉ cần dùng `wire:poll.3s="refreshTableMap"`.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'tableMap' => $this->buildTableMapData(),
        ];
    }

    /**
     * Nạp snapshot mới nhất của sơ đồ bàn trong đúng tenant hiện tại.
     *
     * @return array{
     *     zones: array<int, array{id: int, name: string}>,
     *     tables: array<int, array<string, mixed>>,
     *     groups: array<int, array{id: int|string, name: string, tables: array<int, array<string, mixed>>}>,
     *     statistics: array{total: int, occupied: int, empty: int, todayRevenue: float},
     *     selectedTable: array<string, mixed>|null,
     *     catalog: array<int, array<string, mixed>>,
     *     kitchenPrinters: array<int, array<string, mixed>>,
     *     refreshedAt: string
     * }
     */
    private function buildTableMapData(): array
    {
        $store = $this->currentStore();

        /** @var EloquentCollection<int, DiningTable> $tables */
        $tables = DiningTable::query()
            ->where('store_id', $store->getKey())
            ->with([
                'zone',
                'sessions' => fn ($query) => $query
                    ->where('status', TableSessionStatus::Open->value)
                    ->with(['order.items.product']),
            ])
            ->orderBy('name')
            ->get();

        $allTables = $tables
            ->map(fn (DiningTable $table): array => $this->formatTable($table))
            ->values();

        $visibleTables = $allTables
            ->when(
                $this->zoneFilter !== 'all',
                fn (Collection $items): Collection => $items->where('zone.id', (int) $this->zoneFilter),
            )
            ->when(
                in_array($this->statusFilter, ['empty', 'occupied'], true),
                fn (Collection $items): Collection => $items->where('status', $this->statusFilter),
            )
            ->when(
                filled($this->search),
                fn (Collection $items): Collection => $items->filter(
                    fn (array $table): bool => str_contains(
                        mb_strtolower($table['name']),
                        mb_strtolower(trim($this->search)),
                    ),
                ),
            )
            ->values();

        $zones = TableZone::query()
            ->where('store_id', $store->getKey())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            'zones' => $zones->map(fn (TableZone $zone): array => [
                'id' => (int) $zone->getKey(),
                'name' => $zone->name,
            ])->values()->all(),
            'tables' => $visibleTables->all(),
            'groups' => $this->groupTablesByZone($visibleTables),
            'statistics' => [
                'total' => $allTables->count(),
                'occupied' => $allTables->where('status', 'occupied')->count(),
                'empty' => $allTables->where('status', 'empty')->count(),
                'todayRevenue' => (float) $store->payments()
                    ->where('status', PaymentStatus::Completed->value)
                    ->whereDate('paid_at', today())
                    ->sum('amount'),
            ],
            'selectedTable' => $allTables->firstWhere('id', $this->selectedTableId),
            'catalog' => $this->getCatalog($store),
            'kitchenPrinters' => $this->getKitchenPrinters($store),
            'refreshedAt' => now()->toIso8601String(),
        ];
    }

    /** Chọn một bàn để Blade hiển thị panel order hoặc thao tác mở bàn. */
    public function selectTable(int $tableId): void
    {
        $this->findTableInCurrentStore($tableId);
        $this->selectedTableId = $tableId;
        $this->resetValidation();
    }

    /** Đóng panel chi tiết mà không thay đổi session hoặc order trong database. */
    public function closeTableDetails(): void
    {
        $this->selectedTableId = null;
        $this->draftItems = [];
        $this->checkoutRequestId = null;
        $this->resetValidation();
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

    /** Thanh toán tiền mặt, đóng order/session và làm bàn trở về trạng thái trống. */
    public function checkout(): void
    {
        $order = $this->activeOrderForSelectedTable();
        $this->checkoutRequestId ??= (string) Str::uuid();

        $payment = app(CheckoutTable::class)->handle(
            $order,
            $this->currentUser(),
            clientRequestId: $this->checkoutRequestId,
        );

        // Chỉ xóa UUID sau khi Laravel xác nhận để retry trong cùng component vẫn idempotent.
        $this->checkoutRequestId = null;
        $this->draftItems = [];
        $this->resetValidation();

        Notification::make()
            ->title('Thanh toán thành công')
            ->body('Số tiền: '.number_format((float) $payment->amount, 0, ',', '.').' đ')
            ->success()
            ->send();
    }

    /**
     * Hook rỗng dành cho `wire:poll`; một Livewire request mới tự render lại
     * Page và gọi `getViewData()`, do đó không cần giữ bản sao dữ liệu trong state.
     */
    public function refreshTableMap(): void
    {
        // Không mutate state để filter và bàn đang chọn được giữ nguyên qua mỗi lần poll.
    }

    /** Chuyển model bàn cùng quan hệ active thành payload thuần dành cho Blade. */
    private function formatTable(DiningTable $table): array
    {
        /** @var TableSession|null $session */
        $session = $table->sessions->first();
        $order = $session?->order;
        $items = $order?->items ?? new EloquentCollection;
        $elapsedSeconds = $session?->start_time
            ? max(0, (int) $session->start_time->diffInSeconds(now()))
            : 0;

        return [
            'id' => (int) $table->getKey(),
            'name' => $table->name,
            'zone' => [
                'id' => $table->zone ? (int) $table->zone->getKey() : null,
                'name' => $table->zone?->name ?? 'Chưa phân khu',
            ],
            'status' => $session ? 'occupied' : 'empty',
            'session' => $session ? [
                'id' => (int) $session->getKey(),
                'startTime' => $session->start_time?->toIso8601String(),
                'elapsedSeconds' => $elapsedSeconds,
            ] : null,
            'order' => $order ? [
                'id' => (int) $order->getKey(),
                'code' => $order->code,
                'status' => $order->status->value,
                'total' => (float) $order->total,
                'totalLabel' => number_format((float) $order->total, 0, ',', '.').' đ',
                'hasUnprintedItems' => $items->contains(
                    fn (OrderItem $item): bool => $item->quantity > $item->kitchen_printed_quantity,
                ),
                'items' => $items->map(fn (OrderItem $item): array => [
                    'id' => (int) $item->getKey(),
                    'productId' => (int) $item->product_id,
                    'name' => $item->product?->name ?? 'Sản phẩm đã xóa',
                    'quantity' => $item->quantity,
                    'kitchenPrintedQuantity' => $item->kitchen_printed_quantity,
                    'unitPrice' => (float) $item->unit_price,
                    'subtotal' => (float) $item->unit_price * $item->quantity,
                    'notes' => $item->notes,
                ])->values()->all(),
            ] : null,
        ];
    }

    /** Gom danh sách đã lọc thành từng khu vực để Blade có thể render theo section. */
    private function groupTablesByZone(Collection $tables): array
    {
        return $tables
            ->groupBy(fn (array $table): string => (string) ($table['zone']['id'] ?? 'unassigned'))
            ->map(fn (Collection $zoneTables, string $zoneId): array => [
                'id' => $zoneId === 'unassigned' ? 'unassigned' : (int) $zoneId,
                'name' => $zoneTables->first()['zone']['name'],
                'tables' => $zoneTables->values()->all(),
            ])
            ->values()
            ->all();
    }

    /** Nạp thực đơn đang bán theo nhóm để giao diện thêm món không tự truy vấn database. */
    private function getCatalog(Store $store): array
    {
        return ProductGroup::query()
            ->where('store_id', $store->getKey())
            ->where('is_active', true)
            ->with(['products' => fn ($query) => $query
                ->where('store_id', $store->getKey())
                ->where('is_active', true)
                ->orderBy('name'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ProductGroup $group): array => [
                'id' => (int) $group->getKey(),
                'name' => $group->name,
                'icon' => $group->icon,
                'products' => $group->products->map(fn ($product): array => [
                    'id' => (int) $product->getKey(),
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'priceLabel' => number_format((float) $product->price, 0, ',', '.').' đ',
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /** Trả danh sách máy in bếp đang hoạt động để Blade dựng lựa chọn máy in. */
    private function getKitchenPrinters(Store $store): array
    {
        return Printer::query()
            ->where('store_id', $store->getKey())
            ->where('printer_type', PrinterType::Kitchen->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Printer $printer): array => [
                'id' => (int) $printer->getKey(),
                'name' => $printer->name,
                'ipAddress' => $printer->ip_address,
                'port' => $printer->port,
            ])
            ->values()
            ->all();
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
            ->where('printer_type', PrinterType::Kitchen->value)
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
