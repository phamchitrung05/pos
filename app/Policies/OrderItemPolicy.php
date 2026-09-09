<?php

namespace App\Policies;

use App\Enums\PermissionResource;

/** Bảo vệ dòng món bằng permission và store_id trực tiếp đã được chuẩn hóa. */
class OrderItemPolicy extends BasePolicy
{
    protected PermissionResource $resource = PermissionResource::OrderItem;
}
