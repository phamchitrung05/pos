<?php

namespace App\Enums;

/** Tên role chuẩn dùng khi khai báo và cấp quyền bằng Spatie Permission. */
enum UserRole: string
{
    case Owner = 'owner';
    case Staff = 'staff';
}
