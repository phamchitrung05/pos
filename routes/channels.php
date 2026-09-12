<?php

use App\Broadcasting\StorePosChannel;
use Illuminate\Support\Facades\Broadcast;

/** Chỉ tài khoản có quyền vào Store mới được subscribe dữ liệu POS của Store đó. */
Broadcast::channel('stores.{storeId}.pos', StorePosChannel::class);
