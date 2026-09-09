<?php

namespace App\Policies;

use App\Enums\PermissionResource;

/** Bảo vệ sản phẩm bằng permission và store_id trực tiếp đã được chuẩn hóa. */
class ProductPolicy extends BasePolicy
{
    protected PermissionResource $resource = PermissionResource::Product;
}
