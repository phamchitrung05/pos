<?php

namespace App\Broadcasting;

use App\Models\User;

/** Chính sách subscribe private channel cho dữ liệu POS của một Store. */
final class StorePosChannel
{
    /** Owner được nghe mọi Store; staff chỉ được nghe Store trực thuộc. */
    public function join(User $user, int $storeId): bool
    {
        return $user->canAccessStore($storeId);
    }
}
