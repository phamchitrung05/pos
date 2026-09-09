<?php

namespace App\Policies;

use App\Enums\PermissionResource;

/** Bảo vệ cửa hàng bằng permission dành cho owner và chính id cửa hàng làm tenant. */
class StorePolicy extends BasePolicy
{
    protected PermissionResource $resource = PermissionResource::Store;

    protected string $storeIdPath = 'id';
}
