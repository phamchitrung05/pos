<?php

namespace App\Enums;

/** Phần resource trong tên quyền Spatie theo định dạng `<resource>.<ability>`. */
enum PermissionResource: string
{
    case Store = 'store';
    case User = 'user';
    case TableZone = 'table-zone';
    case DiningTable = 'dining-table';
    case TableSession = 'table-session';
    case ProductGroup = 'product-group';
    case Product = 'product';
    case Order = 'order';
    case OrderItem = 'order-item';
    case Payment = 'payment';
    case Printer = 'printer';
    case PrintJob = 'print-job';

    /** Ghép tên quyền hoàn chỉnh để policy và seeder dùng cùng một quy ước. */
    public function ability(PolicyAbility $ability): string
    {
        return $this->value.'.'.$ability->value;
    }
}
