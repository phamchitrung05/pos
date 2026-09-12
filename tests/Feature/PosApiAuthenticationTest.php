<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Kiểm tra login Sanctum và ràng buộc token với đúng thiết bị Tauri. */
class PosApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_staff_can_login_and_use_a_token_only_from_the_registered_device(): void
    {
        $deviceId = (string) Str::uuid();
        $response = $this->postJson(route('api.pos.login'), [
            'email' => 'staff.tranphu@example.com',
            'password' => 'password',
            'device_id' => $deviceId,
            'device_name' => 'POS quầy 1',
        ])->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.device.id', $deviceId)
            ->assertJsonPath('data.user.store_id', User::query()->where('email', 'staff.tranphu@example.com')->value('store_id'));
        $token = $response->json('data.access_token');

        $this->withToken($token)
            ->withHeader('X-Device-ID', $deviceId)
            ->getJson(route('api.pos.bootstrap'))
            ->assertOk();

        $this->withToken($token)
            ->withHeader('X-Device-ID', (string) Str::uuid())
            ->getJson(route('api.pos.bootstrap'))
            ->assertForbidden();
    }

    public function test_login_rejects_invalid_credentials_and_accounts_without_an_active_store(): void
    {
        $this->postJson(route('api.pos.login'), [
            'email' => 'staff.tranphu@example.com',
            'password' => 'wrong-password',
            'device_id' => (string) Str::uuid(),
            'device_name' => 'POS lỗi',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->postJson(route('api.pos.login'), [
            'email' => 'owner@example.com',
            'password' => 'password',
            'device_id' => (string) Str::uuid(),
            'device_name' => 'Máy owner',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_login_rotates_the_existing_token_for_the_same_device_and_logout_revokes_it(): void
    {
        $deviceId = (string) Str::uuid();
        $credentials = [
            'email' => 'staff.tranphu@example.com',
            'password' => 'password',
            'device_id' => $deviceId,
            'device_name' => 'POS quầy 1',
        ];
        $firstToken = $this->postJson(route('api.pos.login'), $credentials)->assertOk()->json('data.access_token');
        $secondToken = $this->postJson(route('api.pos.login'), $credentials)->assertOk()->json('data.access_token');

        $this->withToken($firstToken)
            ->withHeader('X-Device-ID', $deviceId)
            ->getJson(route('api.pos.bootstrap'))
            ->assertUnauthorized();

        $this->withToken($secondToken)
            ->withHeader('X-Device-ID', $deviceId)
            ->postJson(route('api.pos.logout'))
            ->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'tauri:'.$deviceId]);

        // Mỗi production request dựng guard mới; test kernel cần xóa guard đã cache giữa hai request.
        $this->app['auth']->forgetGuards();

        $this->withToken($secondToken)
            ->withHeader('X-Device-ID', $deviceId)
            ->getJson(route('api.pos.bootstrap'))
            ->assertUnauthorized();
    }
}
