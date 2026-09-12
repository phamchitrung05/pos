<?php

namespace Tests\Feature;

use App\Actions\Pos\AddOrderItems;
use App\Actions\Pos\OpenTableSession;
use App\Broadcasting\StorePosChannel;
use App\Events\PosStateChanged;
use App\Livewire\Pos\TableGrid;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

/** Kiểm tra event realtime phát sau nghiệp vụ và grid vẫn giữ filter riêng. */
class PosRealtimeTest extends TestCase
{
    use RefreshDatabase;

    /** Seed tenant và quyền thật để action chạy qua Laravel Gate như production. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /** Mở bàn và thêm món phải phát metadata đúng Store/bàn cho private channel. */
    public function test_pos_actions_dispatch_tenant_scoped_realtime_events(): void
    {
        Event::fake([PosStateChanged::class]);
        $owner = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $table = DiningTable::query()->firstOrFail();
        $product = Product::query()->where('store_id', $table->store_id)->firstOrFail();
        $session = app(OpenTableSession::class)->handle($table, $owner);

        app(AddOrderItems::class)->handle($session->order, $owner, [[
            'product_id' => $product->id,
            'quantity' => 1,
        ]]);

        Event::assertDispatched(PosStateChanged::class, fn (PosStateChanged $event): bool => $event->storeId === (int) $table->store_id
            && $event->tableId === (int) $table->id
            && $event->change === 'session.opened');
        Event::assertDispatched(PosStateChanged::class, fn (PosStateChanged $event): bool => $event->change === 'order.items-added');
    }

    /** Realtime refresh grid không làm mất khu vực, trạng thái và từ khóa đang lọc. */
    public function test_table_grid_keeps_filters_when_realtime_event_arrives(): void
    {
        $owner = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $store = Store::query()->firstOrFail();
        $table = DiningTable::query()->where('store_id', $store->id)->firstOrFail();
        $this->actingAs($owner);
        Filament::setCurrentPanel('admin');
        Filament::setTenant($store, isQuiet: true);

        Livewire::test(TableGrid::class, ['storeId' => $store->id])
            ->set('zoneFilter', (string) $table->zone_id)
            ->set('statusFilter', 'empty')
            ->set('search', $table->name)
            ->call('handlePosStateChanged', [
                'storeId' => $store->id,
                'tableId' => $table->id,
                'change' => 'order.items-added',
            ])
            ->assertSet('zoneFilter', (string) $table->zone_id)
            ->assertSet('statusFilter', 'empty')
            ->assertSet('search', $table->name);
    }

    /** Grid gọi thẳng Page cha để tránh request trung gian và không tự mutate reactive prop. */
    public function test_table_grid_delegates_selection_directly_to_the_parent_component(): void
    {
        $owner = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $store = Store::query()->firstOrFail();
        $table = DiningTable::query()->where('store_id', $store->id)->firstOrFail();
        $this->actingAs($owner);
        Filament::setCurrentPanel('admin');
        Filament::setTenant($store, isQuiet: true);

        Livewire::test(TableGrid::class, ['storeId' => $store->id, 'selectedTableId' => null])
            ->assertSeeHtml('wire:click="$parent.selectTable('.$table->id.')"')
            ->assertSet('selectedTableId', null)
            ->assertHasNoErrors();
    }

    /** Store ID giả bị từ chối ngay khi mount component, trước mọi query dữ liệu. */
    public function test_table_grid_rejects_a_forged_store_id(): void
    {
        $staff = User::query()->where('email', 'staff.tranphu@example.com')->firstOrFail();
        $otherStore = Store::query()->whereKeyNot($staff->store_id)->firstOrFail();
        $this->actingAs($staff);
        Filament::setCurrentPanel('admin');
        Filament::setTenant(Store::query()->findOrFail($staff->store_id), isQuiet: true);

        Livewire::test(TableGrid::class, ['storeId' => $otherStore->id])->assertNotFound();
    }

    /** Channel policy cho owner qua mọi Store nhưng chặn staff khỏi Store khác. */
    public function test_private_pos_channel_enforces_store_access(): void
    {
        $owner = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $staff = User::query()->where('email', 'staff.tranphu@example.com')->firstOrFail();
        $otherStore = Store::query()->whereKeyNot($staff->store_id)->firstOrFail();
        $channel = app(StorePosChannel::class);

        $this->assertTrue($channel->join($owner, (int) $otherStore->id));
        $this->assertFalse($channel->join($staff, (int) $otherStore->id));
    }
}
