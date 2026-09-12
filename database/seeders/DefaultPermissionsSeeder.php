<?php

namespace Database\Seeders;

use App\Enums\PermissionResource;
use App\Enums\PolicyAbility;
use App\Enums\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Tạo permission production và đồng bộ quyền mặc định cho owner/staff. */
class DefaultPermissionsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionNames = collect(PermissionResource::cases())
            ->flatMap(fn (PermissionResource $resource): array => array_map(
                fn (PolicyAbility $ability): string => $resource->ability($ability),
                PolicyAbility::cases(),
            ))
            ->values();

        $permissionNames->each(fn (string $permission): Permission => Permission::findOrCreate($permission, 'web'));

        $ownerRole = Role::findOrCreate(UserRole::Owner->value, 'web');
        $staffRole = Role::findOrCreate(UserRole::Staff->value, 'web');
        $allPermissions = Permission::query()->where('guard_name', 'web')->get();

        // Owner có toàn quyền trên tất cả resource.
        $ownerRole->syncPermissions($allPermissions);

        // Staff được vận hành POS nhưng không quản trị Store/User.
        $staffPermissions = $allPermissions->filter(function (Permission $permission): bool {
            return ! str_starts_with($permission->name, PermissionResource::Store->value.'.')
                && ! str_starts_with($permission->name, PermissionResource::User->value.'.');
        });
        $staffRole->syncPermissions($staffPermissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
