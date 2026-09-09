<?php

namespace App\Policies;

use App\Enums\PermissionResource;

/** Bảo vệ thanh toán bằng permission và store_id trực tiếp đã được chuẩn hóa. */
class PaymentPolicy extends BasePolicy
{
    protected PermissionResource $resource = PermissionResource::Payment;
}
