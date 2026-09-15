<?php

namespace Tests\Feature;

use App\Filament\Widgets\OperationsOverview;
use App\Filament\Widgets\OwnerRevenueChart;
use App\Filament\Widgets\OwnerRevenueOverview;
use App\Models\Store;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Kiểm tra dashboard phân vai và luôn tổng hợp theo tenant Filament hiện tại. */
class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    /** Seed role, tenant và dữ liệu mẫu trước khi mount từng widget Livewire. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Filament::setCurrentPanel('admin');
    }

    /** Owner nhận widget vận hành, tài chính và biểu đồ của Store đang chọn. */
    public function test_owner_can_render_all_dashboard_widgets(): void
    {
        $owner = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $store = Store::query()->firstOrFail();
        $this->actingAs($owner);
        Filament::setTenant($store, isQuiet: true);

        $this->assertTrue(OwnerRevenueOverview::canView());
        $this->assertTrue(OwnerRevenueChart::canView());
        Livewire::test(OperationsOverview::class)->assertSee('Bàn đang phục vụ');
        Livewire::test(OwnerRevenueOverview::class)->assertSee('Doanh thu hôm nay');
        Livewire::test(OwnerRevenueChart::class)->assertSuccessful();
    }

    /** Staff không được hydrate widget tài chính nhưng vẫn xem được tình trạng vận hành. */
    public function test_staff_only_sees_operational_dashboard_data(): void
    {
        $staff = User::query()->where('email', 'staff.tranphu@example.com')->firstOrFail();
        $store = Store::query()->findOrFail($staff->store_id);
        $this->actingAs($staff);
        Filament::setTenant($store, isQuiet: true);

        $this->assertFalse(OwnerRevenueOverview::canView());
        $this->assertFalse(OwnerRevenueChart::canView());
        Livewire::test(OperationsOverview::class)->assertSee('Đơn hàng đang mở');
    }
}
