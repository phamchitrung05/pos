<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/** Ràng buộc Bearer token POS với đúng UUID thiết bị và Store đang hoạt động. */
final class EnsurePosDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $deviceId = (string) $request->header('X-Device-ID');
        Validator::make(
            ['device_id' => $deviceId],
            ['device_id' => ['required', 'uuid']],
            attributes: ['device_id' => 'X-Device-ID'],
        )->validate();

        $user = $request->user();
        $accessToken = $user instanceof User ? $user->currentAccessToken() : null;

        abort_unless(
            $user instanceof User
            && $accessToken instanceof PersonalAccessToken
            && $user->tokenCan('pos:use')
            && $user->tokenCan("device:{$deviceId}"),
            403,
            'Mã xác thực không được cấp cho thiết bị POS này.',
        );

        $store = $user->store;
        abort_unless($store instanceof Store && $store->is_active, 403, 'Tài khoản chưa thuộc chi nhánh đang hoạt động.');

        $request->attributes->set('pos_device_id', $deviceId);
        $request->attributes->set('pos_store', $store);

        return $next($request);
    }
}
