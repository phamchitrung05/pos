<?php

namespace App\Policies;

use App\Enums\PermissionResource;
use App\Enums\PolicyAbility;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy nền kết hợp quyền Spatie với ranh giới dữ liệu theo cửa hàng.
 *
 * Các thao tác có record chỉ được phép khi người dùng vừa có permission
 * `<resource>.<ability>`, vừa truy cập được cửa hàng sở hữu record đó.
 */
abstract class BasePolicy
{
    /** Resource dùng để tạo tên permission của policy cụ thể. */
    protected PermissionResource $resource;

    /** Đường dẫn thuộc tính từ record tới khóa cửa hàng sở hữu dữ liệu. */
    protected string $storeIdPath = 'store_id';

    /** Cho phép mở danh sách khi người dùng có quyền xem resource. */
    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, PolicyAbility::ViewAny);
    }

    /** Cho phép xem record khi có quyền và record thuộc tenant được truy cập. */
    public function view(User $user, Model $record): bool
    {
        return $this->hasRecordPermission($user, PolicyAbility::View, $record);
    }

    /** Cho phép tạo resource khi người dùng có quyền tạo. */
    public function create(User $user): bool
    {
        return $this->hasPermission($user, PolicyAbility::Create);
    }

    /** Cho phép sửa record khi có quyền và record thuộc tenant được truy cập. */
    public function update(User $user, Model $record): bool
    {
        return $this->hasRecordPermission($user, PolicyAbility::Update, $record);
    }

    /** Cho phép xóa record khi có quyền và record thuộc tenant được truy cập. */
    public function delete(User $user, Model $record): bool
    {
        return $this->hasRecordPermission($user, PolicyAbility::Delete, $record);
    }

    /** Cho phép Filament hiển thị thao tác xóa hàng loạt khi có permission. */
    public function deleteAny(User $user): bool
    {
        return $this->hasPermission($user, PolicyAbility::DeleteAny);
    }

    /** Kiểm tra permission thông qua Laravel Gate đã được Spatie tích hợp. */
    private function hasPermission(User $user, PolicyAbility $ability): bool
    {
        return $user->can($this->resource->ability($ability));
    }

    /** Kiểm tra permission trước, sau đó chặn record nằm ngoài tenant. */
    private function hasRecordPermission(User $user, PolicyAbility $ability, Model $record): bool
    {
        if (! $this->hasPermission($user, $ability)) {
            return false;
        }

        $storeId = data_get($record, $this->storeIdPath);

        return $user->canAccessStore($storeId === null ? null : (int) $storeId);
    }
}
