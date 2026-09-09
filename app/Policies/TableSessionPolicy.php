<?php

namespace App\Policies;

use App\Enums\PermissionResource;

/** Bảo vệ phiên bàn bằng permission và store_id trực tiếp đã được chuẩn hóa. */
class TableSessionPolicy extends BasePolicy
{
    protected PermissionResource $resource = PermissionResource::TableSession;
}
