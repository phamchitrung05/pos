<?php

namespace App\Enums;

/** Trạng thái xử lý một command do thiết bị POS gửi lên. */
enum PosCommandStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
