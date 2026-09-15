<?php

namespace App\Queries\Pos;

use App\Models\TableSession;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/** Đọc nhật ký POS đã chuẩn hóa để modal không phải hiểu cấu trúc Activitylog. */
final class TableSessionActivityReadModel
{
    /**
     * @return Collection<int, array{at: mixed, title: string, description: string, user: string}>
     */
    public function for(TableSession $session): Collection
    {
        return Activity::query()
            ->where('log_name', 'pos')
            ->whereJsonContains('properties->table_session_id', (int) $session->getKey())
            ->with('causer')
            ->latest('created_at')
            ->latest('id')
            ->get()
            ->map(fn (Activity $activity): array => [
                'at' => $activity->created_at,
                'title' => $this->title($activity),
                'description' => $this->description($activity),
                'user' => $activity->causer?->name ?? 'Hệ thống',
            ])
            ->values();
    }

    private function title(Activity $activity): string
    {
        return match ($activity->event) {
            'session.opened' => 'Mở phiên bàn',
            'session.closed' => 'Đóng phiên bàn',
            'session.cancelled' => 'Hủy phiên bàn',
            'order.item.added' => 'Thêm món',
            'order.item.updated' => 'Cập nhật món',
            'order.item.deleted' => 'Xóa món',
            'kitchen.ticket.created' => 'Gửi chế biến',
            'payment.completed' => 'Thanh toán',
            default => $activity->description,
        };
    }

    private function description(Activity $activity): string
    {
        return match ($activity->event) {
            'session.opened' => 'Bàn '.($activity->getExtraProperty('table_name') ?? 'không xác định'),
            'session.closed', 'session.cancelled' => 'Trạng thái phiên đã được cập nhật.',
            'order.item.added' => sprintf(
                '%d × %s (tổng %d)',
                $activity->getExtraProperty('added_quantity', 0),
                $activity->getExtraProperty('product_name') ?? 'Sản phẩm đã xóa',
                $activity->getExtraProperty('resulting_quantity', 0),
            ),
            'order.item.updated' => $this->updatedItemDescription($activity),
            'order.item.deleted' => sprintf(
                '%d × %s',
                $activity->getExtraProperty('quantity', 0),
                $activity->getExtraProperty('product_name') ?? 'Sản phẩm đã xóa',
            ),
            'kitchen.ticket.created' => collect($activity->getExtraProperty('items', []))
                ->map(fn (array $item): string => sprintf(
                    '%d × %s',
                    $item['quantity'] ?? 0,
                    $item['product_name'] ?? 'Sản phẩm đã xóa',
                ))
                ->implode(', '),
            'payment.completed' => number_format((float) $activity->getExtraProperty('amount', 0), 0, ',', '.').' đ',
            default => $activity->description,
        };
    }

    private function updatedItemDescription(Activity $activity): string
    {
        $old = $activity->getExtraProperty('old', []);
        $new = $activity->getExtraProperty('new', []);
        $productName = $activity->getExtraProperty('product_name') ?? 'Sản phẩm đã xóa';

        if (($old['quantity'] ?? null) !== ($new['quantity'] ?? null)) {
            return sprintf('%s: số lượng %d → %d', $productName, $old['quantity'] ?? 0, $new['quantity'] ?? 0);
        }

        if (($old['notes'] ?? null) !== ($new['notes'] ?? null)) {
            return sprintf('%s: cập nhật ghi chú', $productName);
        }

        return sprintf('%s: cập nhật thông tin món', $productName);
    }
}
