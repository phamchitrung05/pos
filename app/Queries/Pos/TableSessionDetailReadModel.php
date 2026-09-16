<?php

namespace App\Queries\Pos;

use App\Enums\PaymentStatus;
use App\Enums\TableSessionStatus;
use App\Models\Payment;
use App\Models\TableSession;
use Illuminate\Support\Collection;

/** Chuẩn hóa toàn bộ dữ liệu modal phiên bàn trước khi chuyển sang Blade. */
final class TableSessionDetailReadModel
{
    public function __construct(private readonly TableSessionActivityReadModel $activityReadModel) {}

    /**
     * @return array{
     *     table_name: string,
     *     zone_name: string|null,
     *     status_label: string,
     *     status_color: string,
     *     opened_date: string,
     *     opened_time: string,
     *     opened_by: string,
     *     item_count: int,
     *     order_total: string,
     *     paid_amount: string,
     *     remaining_amount: string,
     *     events: Collection<int, array{at: mixed, title: string, description: string, user: string}>
     * }
     */
    public function for(TableSession $session): array
    {
        $session->loadMissing(['table.zone', 'openedBy', 'order.items', 'order.payments']);
        $order = $session->order;
        $orderTotal = (int) round((float) ($order?->total ?? 0));
        $paidAmount = $order
            ? (int) round((float) $order->payments
                ->filter(fn (Payment $payment): bool => $payment->status === PaymentStatus::Completed)
                ->sum('amount'))
            : 0;

        return [
            'table_name' => $session->table?->name ?? 'Chưa xác định bàn',
            'zone_name' => $session->table?->zone?->name,
            'status_label' => $session->status->getLabel(),
            'status_color' => $this->statusColor($session->status),
            'opened_date' => $session->start_time?->format('d/m/Y') ?? 'Chưa có',
            'opened_time' => $session->start_time?->format('H:i') ?? 'Chưa có',
            'opened_by' => $session->openedBy?->name ?? 'Hệ thống',
            'item_count' => (int) ($order?->items->sum('quantity') ?? 0),
            'order_total' => $this->formatMoney($orderTotal),
            'paid_amount' => $this->formatMoney($paidAmount),
            'remaining_amount' => $this->formatMoney(max(0, $orderTotal - $paidAmount)),
            'events' => $this->activityReadModel->for($session),
        ];
    }

    /** Màu semantic của Filament giúp badge thích ứng đồng thời với light/dark mode. */
    private function statusColor(TableSessionStatus $status): string
    {
        return match ($status) {
            TableSessionStatus::Open => 'success',
            TableSessionStatus::Closed => 'gray',
            TableSessionStatus::Cancelled => 'danger',
        };
    }

    private function formatMoney(int $amount): string
    {
        return number_format($amount, 0, ',', '.').' đ';
    }
}
