<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Store;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Các chỉ số tài chính chỉ được hydrate cho owner, không chỉ ẩn bằng CSS. */
final class OwnerRevenueOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    /** Chặn staff trước khi widget thực hiện bất kỳ truy vấn doanh thu nào. */
    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isOwner();
    }

    /** Tổng hợp payment hoàn tất trong ngày của đúng tenant đang được owner chọn. */
    protected function getStats(): array
    {
        $store = $this->currentStore();
        $payments = Payment::query()
            ->where('store_id', $store->getKey())
            ->where('status', PaymentStatus::Completed->value)
            ->whereDate('paid_at', today())
            ->get(['amount']);
        $revenue = (float) $payments->sum('amount');
        $average = $payments->isEmpty() ? 0 : $revenue / $payments->count();

        return [
            Stat::make('Doanh thu hôm nay', number_format($revenue, 0, ',', '.').' đ')
                ->description('Chỉ tính thanh toán hoàn tất')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success'),
            Stat::make('Lượt thanh toán', $payments->count())
                ->description('Trong ngày hiện tại')
                ->descriptionIcon(Heroicon::OutlinedReceiptPercent)
                ->color('primary'),
            Stat::make('Giá trị trung bình', number_format($average, 0, ',', '.').' đ')
                ->description('Trên mỗi thanh toán')
                ->descriptionIcon(Heroicon::OutlinedChartBar)
                ->color('info'),
        ];
    }

    /** Tenant là ranh giới bắt buộc của mọi chỉ số tài chính. */
    private function currentStore(): Store
    {
        $store = Filament::getTenant();

        abort_unless($store instanceof Store, 404);

        return $store;
    }
}
