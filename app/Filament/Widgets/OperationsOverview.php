<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\PrintJobStatus;
use App\Enums\TableSessionStatus;
use App\Filament\Pages\Pos\TableMap;
use App\Filament\Resources\PrintJobs\PrintJobResource;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\PrintJob;
use App\Models\Store;
use App\Models\TableSession;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Tổng quan vận hành tức thời cho cả owner và nhân viên tại tenant đang chọn. */
final class OperationsOverview extends StatsOverviewWidget
{
    /** Poll vừa đủ nhanh cho vận hành nhưng nhẹ hơn sơ đồ bàn chi tiết. */
    protected ?string $pollingInterval = '15s';

    /** Mỗi query đều scope trực tiếp theo Store vì widget không đi qua Resource query. */
    protected function getStats(): array
    {
        $store = $this->currentStore();
        $occupied = TableSession::query()
            ->where('store_id', $store->getKey())
            ->where('status', TableSessionStatus::Open->value)
            ->count();
        $totalTables = DiningTable::query()->where('store_id', $store->getKey())->count();
        $openOrders = Order::query()
            ->where('store_id', $store->getKey())
            ->where('status', OrderStatus::Open->value)
            ->count();
        $queueCount = PrintJob::query()
            ->where('store_id', $store->getKey())
            ->whereIn('status', [PrintJobStatus::Pending->value, PrintJobStatus::Printing->value])
            ->count();
        $failedCount = PrintJob::query()
            ->where('store_id', $store->getKey())
            ->where('status', PrintJobStatus::Failed->value)
            ->count();

        return [
            Stat::make('Bàn đang phục vụ', $occupied.'/'.$totalTables)
                ->description(max(0, $totalTables - $occupied).' bàn đang trống')
                ->descriptionIcon(Heroicon::OutlinedTableCells)
                ->color('success')
                ->url(TableMap::getUrl(panel: 'admin', tenant: $store)),
            Stat::make('Đơn hàng đang mở', $openOrders)
                ->description('Đi tới sơ đồ bàn để xử lý')
                ->descriptionIcon(Heroicon::OutlinedShoppingCart)
                ->color('primary')
                ->url(TableMap::getUrl(panel: 'admin', tenant: $store)),
            Stat::make('Hàng đợi in', $queueCount)
                ->description($failedCount.' lệnh đang lỗi')
                ->descriptionIcon(Heroicon::OutlinedPrinter)
                ->color($failedCount > 0 ? 'danger' : 'warning')
                ->url(PrintJobResource::getUrl('index', panel: 'admin', tenant: $store)),
        ];
    }

    /** Lấy tenant đã qua middleware Filament, không suy luận Store từ request input. */
    private function currentStore(): Store
    {
        $store = Filament::getTenant();

        abort_unless($store instanceof Store, 404);

        return $store;
    }
}
