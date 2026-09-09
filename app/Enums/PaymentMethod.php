<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Phương thức thanh toán mà hệ thống POS đang hỗ trợ.
 *
 * MVP chỉ thu tiền mặt. Các phương thức chuyển khoản hoặc cổng thanh toán sẽ
 * được thêm bằng case mới khi đã có quy trình đối soát tương ứng.
 */
enum PaymentMethod: string implements HasLabel
{
    case Cash = 'cash';

    /** Nhãn hiển thị trên lịch sử giao dịch và hóa đơn. */
    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => 'Tiền mặt',
        };
    }
}
