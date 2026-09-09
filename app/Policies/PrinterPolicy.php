<?php

namespace App\Policies;

use App\Enums\PermissionResource;

/** Giới hạn quyền trên máy in theo store_id trực tiếp của record. */
class PrinterPolicy extends BasePolicy
{
    protected PermissionResource $resource = PermissionResource::Printer;
}
