<?php

namespace App\Enums;

/** Các thao tác chuẩn mà Filament kiểm tra trên model policy. */
enum PolicyAbility: string
{
    case ViewAny = 'viewAny';
    case View = 'view';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case DeleteAny = 'deleteAny';
}
