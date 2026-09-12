<?php

namespace App\Policies;

use App\Enums\PermissionResource;
use App\Models\Printer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Giới hạn quyền trên máy in theo store_id trực tiếp của record. */
class PrinterPolicy extends BasePolicy
{
    protected PermissionResource $resource = PermissionResource::Printer;

    /** Không xóa máy đã có lịch sử PrintJob; hãy tắt `is_active` để bảo toàn audit. */
    public function delete(User $user, Model $record): bool
    {
        return $record instanceof Printer
            && ! $record->printJobs()->exists()
            && parent::delete($user, $record);
    }

    /** Bulk delete bị tắt vì không thể kiểm tra an toàn lịch sử của từng máy trong menu chung. */
    public function deleteAny(User $user): bool
    {
        return false;
    }
}
