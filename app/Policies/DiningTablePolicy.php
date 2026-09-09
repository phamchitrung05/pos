<?php

namespace App\Policies;

use App\Enums\PermissionResource;

/** Giới hạn quyền trên bàn ăn theo store_id trực tiếp của record. */
class DiningTablePolicy extends BasePolicy
{
    protected PermissionResource $resource = PermissionResource::DiningTable;
}
