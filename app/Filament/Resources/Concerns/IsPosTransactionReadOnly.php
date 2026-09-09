<?php

namespace App\Filament\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Biến Resource giao dịch thành màn hình chỉ đọc trong Filament.
 *
 * Order, dòng món, payment, phiên bàn và lệnh in không phải dữ liệu danh mục.
 * Nếu cho phép CRUD trực tiếp, người dùng có thể bỏ qua transaction và invariant
 * trong `App\Actions\Pos`. Giao diện POS phải gọi action chuyên trách; Resource
 * tiêu chuẩn chỉ dùng để theo dõi và đối soát dữ liệu đã phát sinh.
 */
trait IsPosTransactionReadOnly
{
    /** Không hiển thị hoặc cho truy cập trang tạo record CRUD trực tiếp. */
    public static function canCreate(): bool
    {
        return false;
    }

    /** Không cho sửa riêng lẻ một record vì thay đổi phải đi qua action nghiệp vụ. */
    public static function canEdit(Model $record): bool
    {
        return false;
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
