<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

/**
 * Xác thực private channel cho ứng dụng POS/Tauri bằng cùng Bearer token và
 * X-Device-ID đã bảo vệ các API POS. Route broadcast mặc định của Laravel dùng
 * middleware web, không phù hợp với thiết bị POS không có cookie session.
 */
final class PosBroadcastAuthController extends Controller
{
    /**
     * Ủy quyền channel cho Broadcast manager; StorePosChannel tiếp tục là nơi
     * duy nhất quyết định user có được nghe store tương ứng hay không.
     */
    public function authenticate(Request $request): mixed
    {
        return Broadcast::auth($request);
    }
}
