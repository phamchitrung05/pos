<?php

namespace App\Policies;

use App\Enums\PermissionResource;

/** Giới hạn quyền trên nhóm sản phẩm theo store_id trực tiếp của record. */
class ProductGroupPolicy extends BasePolicy
{
    protected PermissionResource $resource = PermissionResource::ProductGroup;
}
