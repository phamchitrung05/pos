<?php

namespace App\Policies;

use App\Enums\PermissionResource;

/** Bảo vệ lệnh in bằng permission và store_id trực tiếp đã được chuẩn hóa. */
class PrintJobPolicy extends BasePolicy
{
    protected PermissionResource $resource = PermissionResource::PrintJob;
}
