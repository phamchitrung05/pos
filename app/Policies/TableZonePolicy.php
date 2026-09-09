<?php

namespace App\Policies;

use App\Enums\PermissionResource;

/** Giới hạn quyền trên khu vực bàn theo store_id trực tiếp của record. */
class TableZonePolicy extends BasePolicy
{
    protected PermissionResource $resource = PermissionResource::TableZone;
}
