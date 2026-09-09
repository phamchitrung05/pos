<?php

namespace App\Policies;

use App\Enums\PermissionResource;

/** Bảo vệ tài khoản bằng user permission; store_id null dành cho owner hệ thống. */
class UserPolicy extends BasePolicy
{
    protected PermissionResource $resource = PermissionResource::User;
}
