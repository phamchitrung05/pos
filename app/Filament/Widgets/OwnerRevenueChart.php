<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

/** Biểu đồ doanh thu bảy ngày của chi nhánh hiện tại dành riêng cho owner. */
final class OwnerRevenueChart extends ChartWidget
{
    protected ?string $heading = 'Doanh thu 7 ngày gần nhất';

    protected ?string $description = 'Chỉ bao gồm các payment đã hoàn tất';

    protected ?string $pollingInterval = '60s';

    protected string $color = 'success';

    /** Không render component tài chính vào response của tài khoản staff. */
    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isOwner();
    }

    /** Gom dữ liệu trong PHP để tương thích cả MySQL production và SQLite test. */
    protected function getData(): array
    {
        $store = Filament::getTenant();
        abort_unless($store instanceof Store, 404);

        $startDate = CarbonImmutable::today()->subDays(6);
        $revenueByDate = Payment::query()
            ->where('store_id', $store->getKey())
            ->where('status', PaymentStatus::Completed->value)
            ->where('paid_at', '>=', $startDate->startOfDay())
            ->get(['amount', 'paid_at'])
            ->groupBy(fn (Payment $payment): string => $payment->paid_at->toDateString())
            ->map(fn ($payments): float => (float) $payments->sum('amount'));
        $days = collect(range(0, 6))->map(fn (int $offset): CarbonImmutable => $startDate->addDays($offset));

        return [
            'datasets' => [[
                'label' => 'Doanh thu',
                'data' => $days->map(fn (CarbonImmutable $day): float => $revenueByDate->get($day->toDateString(), 0))->all(),
                'borderColor' => '#10b981',
                'backgroundColor' => 'rgba(16, 185, 129, 0.15)',
                'fill' => true,
                'tension' => 0.35,
            ]],
            'labels' => $days->map(fn (CarbonImmutable $day): string => $day->format('d/m'))->all(),
        ];
    }

    /** Dùng line chart để thể hiện xu hướng thay vì so sánh danh mục rời rạc. */
    protected function getType(): string
    {
        return 'line';
    }
}
