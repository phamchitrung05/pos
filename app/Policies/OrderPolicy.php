<?php

namespace App\Policies;

use App\Enums\PermissionResource;

/** Bảo vệ đơn hàng bằng permission và store_id trực tiếp đã được chuẩn hóa. */
class OrderPolicy extends BasePolicy
{
    protected PermissionResource $resource = PermissionResource::Order;
}
