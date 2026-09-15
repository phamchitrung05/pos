<?php

namespace App\Filament\Resources\Concerns;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Giới hạn CRUD trực tiếp trên các Resource giao dịch trong Filament.
 *
 * Order, dòng món, payment, phiên bàn và lệnh in không phải dữ liệu danh mục.
 * Nếu cho phép CRUD trực tiếp, người dùng có thể bỏ qua transaction và invariant
 * trong `App\Actions\Pos`. Giao diện POS phải gọi action chuyên trách; Resource
 * tiêu chuẩn chủ yếu dùng để theo dõi và đối soát dữ liệu đã phát sinh. Owner
 * vẫn được phép sửa khi cần khắc phục dữ liệu vận hành.
 */
trait IsPosTransactionReadOnly
{
    /** Không hiển thị hoặc cho truy cập trang tạo record CRUD trực tiếp. */
    public static function canCreate(): bool
    {
        return false;
    }

    /** Chỉ owner có quyền update và truy cập tenant của record mới được sửa trực tiếp. */
    public static function canEdit(Model $record): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && $user->isOwner()
            && static::getEditAuthorizationResponse($record)->allowed();
    }

    /** Không xóa giao dịch để giữ lịch sử order, thanh toán và in bill. */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /** Chặn xóa hàng loạt vì thao tác này cũng bỏ qua các invariant nghiệp vụ. */
    public static function canDeleteAny(): bool
    {
        return false;
    }
}
