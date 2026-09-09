<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Stores\StoreResource;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Store;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Kiểm tra xuyên suốt ranh giới tenant và quyền owner/staff.
 *
 * Các test dùng DatabaseSeeder thật để đồng thời bảo vệ cấu hình role,
 * permission, dữ liệu mẫu và cách Filament nhận diện Store hiện tại.
 */
class StoreTenancyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** Tạo lại đầy đủ role, permission và dữ liệu hai chi nhánh trước mỗi test. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /** Owner phải truy cập được mọi Store, còn staff chỉ truy cập Store đã gán. */
    public function test_owner_and_staff_receive_the_correct_tenant_lists(): void
    {
        $panel = filament()->getPanel('admin');
        $owner = $this->owner();
        $staff = $this->staff();
        $otherStore = Store::query()->whereKeyNot($staff->store_id)->firstOrFail();

        $this->assertCount(2, $owner->getTenants($panel));
        $this->assertCount(1, $staff->getTenants($panel));
        $this->assertTrue($owner->canAccessTenant($otherStore));
        $this->assertFalse($staff->canAccessTenant($otherStore));
        $this->assertTrue($staff->canAccessTenant($staff->store));
    }

    /** Policy phải kết hợp permission với store_id thay vì chỉ kiểm tra role. */
    public function test_model_policy_blocks_staff_from_records_in_another_store(): void
    {
        $owner = $this->owner();
        $staff = $this->staff();
        $ownProduct = Product::query()->where('store_id', $staff->store_id)->firstOrFail();
        $otherProduct = Product::query()->where('store_id', '!=', $staff->store_id)->firstOrFail();

        $this->assertTrue($owner->can('update', $otherProduct));
        $this->assertTrue($staff->can('update', $ownProduct));
        $this->assertFalse($staff->can('update', $otherProduct));

        // Staff không có permission quản trị Store hoặc User, dù hai model
        // này vẫn tồn tại trong cùng panel với các resource nghiệp vụ.
        $this->assertFalse($staff->can('viewAny', Store::class));
        $this->assertFalse($staff->can('viewAny', User::class));
        $this->assertTrue($owner->can('viewAny', Store::class));
        $this->assertTrue($owner->can('create', User::class));
    }

    /** Middleware Filament phải trả 404 khi staff đoán tenant ID trên URL. */
    public function test_staff_cannot_open_another_store_by_changing_the_url(): void
    {
        $staff = $this->staff();
        $otherStore = Store::query()->whereKeyNot($staff->store_id)->firstOrFail();

        $this->actingAs($staff)
            ->get(ProductResource::getUrl(panel: 'admin', tenant: $otherStore))
            ->assertNotFound();

        $this->actingAs($staff)
            ->get(ProductResource::getUrl(panel: 'admin', tenant: $staff->store))
            ->assertOk();
    }

    /** Owner phải mở được resource của từng chi nhánh qua tenant switcher. */
    public function test_owner_can_open_every_store(): void
    {
        $owner = $this->owner();

        foreach (Store::query()->get() as $store) {
            $this->actingAs($owner)
                ->get(ProductResource::getUrl(panel: 'admin', tenant: $store))
                ->assertOk();
        }
    }

    /** Chỉ owner được mở màn hình quản trị Store và tạo staff cho tenant đang chọn. */
    public function test_only_owner_can_open_store_and_user_management_pages(): void
    {
        $owner = $this->owner();
        $staff = $this->staff();
        $tenant = $staff->store;

        $this->actingAs($owner)
            ->get(StoreResource::getUrl(panel: 'admin', tenant: $tenant))
            ->assertOk();
        $this->actingAs($owner)
            ->get(UserResource::getUrl('create', panel: 'admin', tenant: $tenant))
            ->assertOk();

        $this->actingAs($staff)
            ->get(UserResource::getUrl(panel: 'admin', tenant: $tenant))
            // Filament chuyển người dùng về trang hợp lệ thay vì render
            // resource không có quyền viewAny.
            ->assertRedirect();
    }

    /** Bảng user của owner phải hiển thị tài khoản thuộc tất cả chi nhánh. */
    public function test_owner_can_see_all_users_in_the_global_user_table(): void
    {
        $owner = $this->owner();
        $tenant = Store::query()->firstOrFail();

        $response = $this->actingAs($owner)
            ->get(UserResource::getUrl(panel: 'admin', tenant: $tenant));

        $response->assertOk();

        foreach (User::query()->pluck('name') as $userName) {
            $response->assertSee($userName);
        }
    }

    /** Owner tạo staff qua Filament phải tự gán tenant và không thể tạo role owner từ form. */
    public function test_owner_can_create_a_staff_user_for_the_selected_store(): void
    {
        $owner = $this->owner();
        $tenant = Store::query()->whereKeyNot($this->staff()->store_id)->firstOrFail();
        $staffRole = Role::findByName(UserRole::Staff->value);

        $this->actingAs($owner);
        Filament::setCurrentPanel('admin');
        Filament::setTenant($tenant, isQuiet: true);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Nhân viên mới',
                'email' => 'new.staff@example.com',
                'password' => 'password',
                'roles' => [$staffRole->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $createdUser = User::query()->where('email', 'new.staff@example.com')->firstOrFail();

        $this->assertSame($tenant->id, $createdUser->store_id);
        $this->assertTrue($createdUser->hasRole(UserRole::Staff->value));
        $this->assertFalse($createdUser->hasRole(UserRole::Owner->value));
    }

    /** Bảng Filament chỉ được render sản phẩm thuộc tenant trên request hiện tại. */
    public function test_filament_resource_table_is_scoped_to_the_current_store(): void
    {
        $staff = $this->staff();
        $ownProduct = Product::query()->where('store_id', $staff->store_id)->firstOrFail();
        $otherProduct = Product::query()->where('store_id', '!=', $staff->store_id)->firstOrFail();

        // Dùng tên không thể là tiền tố của sản phẩm khác; ví dụ "Nước cam 1"
        // trước đây làm assertDontSee() khớp nhầm với chuỗi "Nước cam 10".
        $otherProduct->forceFill(['name' => 'Sản phẩm tenant khác duy nhất'])->save();

        $this->actingAs($staff)
            ->get(ProductResource::getUrl(panel: 'admin', tenant: $staff->store))
            ->assertOk()
            ->assertSee($ownProduct->name)
            ->assertDontSee($otherProduct->name);
    }

    /** Model event phải tự lấy Store từ quan hệ cha khi code chạy ngoài Filament. */
    public function test_model_derives_store_from_its_parent_relation(): void
    {
        $group = ProductGroup::query()->firstOrFail();

        $product = Product::create([
            'product_group_id' => $group->id,
            'name' => 'Sản phẩm kiểm tra tenant',
            'price' => 50000,
            'is_active' => true,
            'is_sku' => false,
        ]);

        $this->assertSame($group->store_id, $product->store_id);
    }

    /** Model event phải chặn việc ghép sản phẩm và đơn hàng khác Store. */
    public function test_model_rejects_cross_store_relationships(): void
    {
        $order = Order::query()->firstOrFail();
        $foreignProduct = Product::query()->where('store_id', '!=', $order->store_id)->firstOrFail();

        $this->expectException(ValidationException::class);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $foreignProduct->id,
            'quantity' => 1,
            'unit_price' => $foreignProduct->price,
        ]);
    }

    /** Lấy tài khoản owner chuẩn do seeder tạo. */
    private function owner(): User
    {
        return User::query()->where('email', 'owner@example.com')->firstOrFail();
    }

    /** Lấy tài khoản staff chuẩn do seeder tạo. */
    private function staff(): User
    {
        return User::query()->where('email', 'staff.tranphu@example.com')->firstOrFail();
    }
}
