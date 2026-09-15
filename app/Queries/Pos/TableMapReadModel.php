<?php

namespace App\Queries\Pos;

use App\Enums\PrinterType;
use App\Enums\TableSessionStatus;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\ProductGroup;
use App\Models\Store;
use App\Models\TableSession;
use App\Models\TableZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Read model chuyên dựng dữ liệu màn hình POS.
 *
 * Query được tách khỏi Livewire để Page và component realtime dùng chung một
 * định nghĩa trạng thái bàn, không lặp lại nghiệp vụ trình bày hoặc query tenant.
 */
final class TableMapReadModel
{
    /**
     * Dựng phần tổng quan được TableGrid cập nhật độc lập qua WebSocket.
     *
     * @return array<string, mixed>
     */
    public function overview(Store $store, string $zoneFilter = 'all', string $statusFilter = 'all', string $search = ''): array
    {
        $allTables = $this->tableQuery($store)
            ->get()
            ->map(fn (DiningTable $table): array => $this->formatTable($table))
            ->values();
        $visibleTables = $allTables
            ->when(
                $zoneFilter !== 'all',
                fn (Collection $items): Collection => $items->where('zone.id', (int) $zoneFilter),
            )
            ->when(
                in_array($statusFilter, ['empty', 'occupied'], true),
                fn (Collection $items): Collection => $items->where('status', $statusFilter),
            )
            ->when(
                filled($search),
                fn (Collection $items): Collection => $items->filter(
                    fn (array $table): bool => str_contains(mb_strtolower($table['name']), mb_strtolower(trim($search))),
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
                'tableCount' => $allTables->where('zone.id', (int) $zone->getKey())->count(),
            ])->values()->all(),
            'tables' => $visibleTables->all(),
            'groups' => $this->groupTablesByZone($visibleTables),
            'statistics' => [
                'total' => $allTables->count(),
                'occupied' => $allTables->where('status', 'occupied')->count(),
                'empty' => $allTables->where('status', 'empty')->count(),
                'revenue' => $allTables->sum(fn (array $table): float => (float) ($table['order']['total'] ?? 0)),
            ],
            'refreshedAt' => now()->toIso8601String(),
        ];
    }

    /** Nạp riêng bàn đang chọn để refresh panel không kéo theo toàn bộ sơ đồ. */
    public function selectedTable(Store $store, ?int $tableId): ?array
    {
        if ($tableId === null) {
            return null;
        }

        /** @var DiningTable|null $table */
        $table = $this->tableQuery($store)->whereKey($tableId)->first();

        return $table ? $this->formatTable($table) : null;
    }

    /** Dựng cùng contract chi tiết bàn cho một order lịch sử trong modal chỉ đọc. */
    public function orderDetails(Order $order): array
    {
        $order->loadMissing(['tableSession.table.zone', 'items.product']);

        return $this->formatTableDetails(
            table: $order->tableSession?->table,
            session: $order->tableSession,
            order: $order,
        );
    }

    /** Nạp thực đơn đang bán theo nhóm cho panel order, không nằm trong polling grid. */
    public function catalog(Store $store): array
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

    /** Trả máy in bếp đang hoạt động cho panel tạo phiếu bếp. */
    public function kitchenPrinters(Store $store): array
    {
        return Printer::query()
            ->where('store_id', $store->getKey())
            ->whereIn('printer_type', [PrinterType::Kitchen->value, PrinterType::Both->value])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Printer $printer): array => [
                'id' => (int) $printer->getKey(),
                'name' => $printer->name,
                'ipAddress' => $printer->ip_address,
                'port' => $printer->port,
                'paperWidthMm' => $printer->paper_width_mm->value,
            ])
            ->values()
            ->all();
    }

    /** Query chuẩn cho bàn cùng active session/order, luôn giới hạn bằng store_id. */
    private function tableQuery(Store $store): Builder
    {
        return DiningTable::query()
            ->where('store_id', $store->getKey())
            ->with([
                'zone',
                'sessions' => fn ($query) => $query
                    ->where('status', TableSessionStatus::Open->value)
                    ->with(['order.items.product']),
            ])
            ->orderBy('name');
    }

    /** Chuyển model đã eager-load thành payload thuần, ổn định cho Livewire morph. */
    private function formatTable(DiningTable $table): array
    {
        /** @var TableSession|null $session */
        $session = $table->sessions->first();
        $order = $session?->order;

        return $this->formatTableDetails($table, $session, $order);
    }

    /** Chuyển bàn, phiên và order thành contract dùng chung cho các modal POS. */
    private function formatTableDetails(?DiningTable $table, ?TableSession $session, ?Order $order): array
    {
        $items = $order?->items ?? new EloquentCollection;
        $elapsedSeconds = $session?->start_time
            ? max(0, (int) $session->start_time->diffInSeconds($session->end_time ?? now()))
            : 0;

        return [
            'id' => $table ? (int) $table->getKey() : null,
            'name' => $table?->name ?? 'Bàn đã xóa',
            'zone' => [
                'id' => $table?->zone ? (int) $table->zone->getKey() : null,
                'name' => $table?->zone?->name ?? 'Chưa phân khu',
            ],
            'status' => $session?->status === TableSessionStatus::Open ? 'occupied' : 'empty',
            'session' => $session ? [
                'id' => (int) $session->getKey(),
                'status' => $session->status->value,
                'statusLabel' => $session->status === TableSessionStatus::Open ? 'Đang có khách' : $session->status->getLabel(),
                'startTime' => $session->start_time?->toIso8601String(),
                'startTimeLabel' => $session->start_time?->format('H:i - d/m/Y'),
                'elapsedSeconds' => $elapsedSeconds,
                'elapsedLabel' => sprintf('%02d:%02d', intdiv($elapsedSeconds, 3600), intdiv($elapsedSeconds % 3600, 60)),
                'elapsedFullLabel' => sprintf('%02d:%02d:%02d', intdiv($elapsedSeconds, 3600), intdiv($elapsedSeconds % 3600, 60), $elapsedSeconds % 60),
            ] : null,
            'order' => $order ? [
                'id' => (int) $order->getKey(),
                'code' => $order->code,
                'status' => $order->status->value,
                'notes' => $order->notes,
                'total' => (float) $order->total,
                'totalLabel' => number_format((float) $order->total, 0, ',', '.').' đ',
                'itemCount' => $items->sum('quantity'),
                'hasUnprintedItems' => $items->contains(
                    fn (OrderItem $item): bool => $item->quantity > $item->kitchen_printed_quantity,
                ),
                'items' => $items->map(fn (OrderItem $item): array => [
                    'id' => (int) $item->getKey(),
                    'productId' => (int) $item->product_id,
                    'name' => $item->product?->name ?? 'Sản phẩm đã xóa',
                    'code' => 'SP'.str_pad((string) $item->product_id, 3, '0', STR_PAD_LEFT),
                    'quantity' => $item->quantity,
                    'kitchenPrintedQuantity' => $item->kitchen_printed_quantity,
                    'unitPrice' => (float) $item->unit_price,
                    'unitPriceLabel' => number_format((float) $item->unit_price, 0, ',', '.').' đ',
                    'subtotal' => (float) $item->unit_price * $item->quantity,
                    'subtotalLabel' => number_format((float) $item->unit_price * $item->quantity, 0, ',', '.').' đ',
                    'notes' => $item->notes,
                ])->values()->all(),
            ] : null,
        ];
    }

    /** Gom bàn đã lọc thành section khu vực cho grid. */
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
}
