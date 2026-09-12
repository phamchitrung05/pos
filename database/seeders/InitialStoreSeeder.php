<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

/** Tạo một cửa hàng mặc định để owner có tenant ngay lần triển khai đầu tiên. */
class InitialStoreSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent: chạy lại trên host không tạo thêm cửa hàng trùng tên.
        Store::firstOrCreate(
            ['name' => 'POS Malibu'],
            [
                'address' => null,
                'phone' => null,
                'email' => null,
                'opening_hours' => null,
                'is_active' => true,
            ],
        );
    }
}
