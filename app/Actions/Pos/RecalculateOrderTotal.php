<?php

namespace App\Actions\Pos;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Validation\ValidationException;

/** Tính lại tổng order từ dữ liệu dòng món đang được lưu trong database. */
final class RecalculateOrderTotal
{
    /**
     * Ghi tổng do server tính, tuyệt đối không sử dụng giá trị `total` từ form hoặc API.
     *
     * Phép nhân và cộng được thực hiện tại database trên cột DECIMAL để hạn chế
     * sai số số thực. Giá trị cuối cùng được chuẩn hóa hai chữ số thập phân trước
     * khi lưu trở lại order.
     */
    public function handle(Order $order): Order
    {
        $total = OrderItem::query()
            ->where('order_id', $order->getKey())
            ->selectRaw('COALESCE(SUM(quantity * unit_price), 0) AS total')
            ->value('total');

        if ((float) $total > 99_999_999.99) {
            throw ValidationException::withMessages([
                'total' => 'Tổng tiền order vượt quá giới hạn lưu trữ 99.999.999,99.',
            ]);
        }

        $order->forceFill([
            'total' => number_format((float) $total, 2, '.', ''),
        ])->save();

        return $order->refresh();
    }
}
