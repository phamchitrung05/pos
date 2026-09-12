<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tạo tài khoản quản trị ban đầu để chạy trên môi trường production.
 *
 * Seeder này độc lập với DatabaseSeeder (bộ dữ liệu demo), chỉ tạo đúng một
 * tài khoản Owner và role tương ứng nên có thể chạy an toàn trên host mà
 * không ảnh hưởng tới dữ liệu đã có. Chạy bằng lệnh:
 *
 *   php artisan db:seed --class=AdminUserSeeder
 */
class AdminUserSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Owner không gắn vào Store cụ thể nên có thể chuyển giữa mọi chi
        // nhánh; mật khẩu được model User tự băm qua cast hashed.
        $admin = User::updateOrCreate(
            ['email' => 'phamchitrung05@gmail.com'],
            [
                'store_id' => null,
                'name' => 'Phạm Chí Trung',
                'email_verified_at' => now(),
                'password' => 'Thangbiqn1@',
            ],
        );

        // Xóa cache permission trước khi tạo role để Spatie không dùng dữ
        // liệu cũ, và đảm bảo role Owner tồn tại trên guard web.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $ownerRole = Role::findOrCreate(UserRole::Owner->value, 'web');

        // updateOrCreate có thể gặp tài khoản đã tồn tại; syncRoles thay vì
        // assignRole để idempotent, không bị lỗi trùng bản ghi model_has_roles.
        $admin->syncRoles([$ownerRole]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
