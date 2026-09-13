<?php

namespace App\PrintTemplates;

use App\Models\Printer;
use App\Models\Store;
use App\Models\User;

/** Tạo snapshot hóa đơn mẫu để kiểm tra kết nối và khổ giấy máy in. */
final class ReceiptTestPrintTemplate
{
    /** @return array<string, mixed> */
    public function render(Store $store, Printer $printer, User $actor): array
    {
        return [
            'version' => 1,
            'type' => 'receipt',
            'store' => [
                'id' => (int) $store->getKey(),
                'name' => $store->name,
                'address' => $store->address,
                'phone' => $store->phone,
            ],
            'document' => [
                'paper_width_mm' => $printer->paper_width_mm->value,
                'dots_per_line' => $printer->paper_width_mm->dotsPerLine(),
                'locale' => 'vi-VN',
                 'render_mode' => 'raw',
            ],
            'order' => [
                'id' => 0,
                'code' => 'TEST-PRINT',
                'table' => 'Bàn kiểm tra',
                'items' => [
                    [
                        'name' => 'Cà phê sữa đá',
                        'quantity' => 1,
                        'unit_price' => 35_000,
                        'subtotal' => 35_000,
                        'notes' => 'Phiếu in thử tiếng Việt',
                    ],
                    [
                        'name' => 'Bánh mì đặc biệt',
                        'quantity' => 2,
                        'unit_price' => 25_000,
                        'subtotal' => 50_000,
                        'notes' => null,
                    ],
                ],
                'total' => 85_000,
            ],
            'payment' => [
                'id' => 0,
                'method' => 'cash',
                'amount' => 85_000,
                'paid_at' => now()->toIso8601String(),
                'received_by' => $actor->name,
            ],
        ];
    }
}
