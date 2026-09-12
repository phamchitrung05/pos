<?php

namespace App\Enums;

/** Các thao tác ghi dữ liệu mà Tauri được phép gửi bằng command idempotent. */
enum PosCommandType: string
{
    case OpenTable = 'open_table';
    case AddOrderItems = 'add_order_items';
    case UpdateOrderItem = 'update_order_item';
    case CreateKitchenTicket = 'create_kitchen_ticket';
    case Checkout = 'checkout';
}
