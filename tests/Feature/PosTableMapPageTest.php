<?php

namespace Tests\Feature;

use App\Enums\PrinterType;
use App\Enums\PrintType;
use App\Enums\TableSessionStatus;
use App\Filament\Pages\Pos\TableMap;
use App\Livewire\Pos\TableGrid;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Printer;
use App\Models\Product;
use App\Models\Store;
use App\Models\TableSession;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Kiểm tra standalone POS Page điều phối đúng các action mà không phụ thuộc giao diện Blade. */
class PosTableMapPageTest extends TestCase
{
    use RefreshDatabase;

    /** Tạo dữ liệu hai tenant và quyền thật trước mỗi test tích hợp Filament. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /** Page phải được Filament đăng ký độc lập và nạp đúng snapshot của tenant hiện tại. */
    public function test_owner_can_open_the_standalone_page_and_read_table_map_data(): void
    {
        [$owner, $store] = $this->ownerAndStore();

        $this->actingAs($owner)
            ->get(TableMap::getUrl(panel: 'admin', tenant: $store))
            ->assertOk()
            ->assertSee(DiningTable::query()->where('store_id', $store->id)->firstOrFail()->name)
            ->assertDontSee('Khu A - Tầng trệt');

        Filament::setCurrentPanel('admin');
        Filament::setTenant($store, isQuiet: true);

        $component = Livewire::test(TableMap::class);
        $pageData = $component->viewData('tableMap');
        $tableMap = Livewire::test(TableGrid::class, ['storeId' => $store->id])->viewData('tableMap');

        $this->assertSame(DiningTable::query()->where('store_id', $store->id)->count(), $tableMap['statistics']['total']);
        $this->assertCount($tableMap['statistics']['total'], $tableMap['tables']);
        $this->assertNotEmpty($pageData['catalog']);
        $this->assertNotEmpty($pageData['kitchenPrinters']);
    }

    /** Page cha xác thực bàn, cập nhật state rồi yêu cầu Filament mở modal chi tiết. */
    public function test_selecting_a_table_opens_the_table_modal(): void
    {
        [$owner, $store] = $this->ownerAndStore();
        $table = DiningTable::query()->where('store_id', $store->id)->firstOrFail();
        $this->actingAs($owner);
        Filament::setCurrentPanel('admin');
        Filament::setTenant($store, isQuiet: true);

        Livewire::test(TableMap::class)
            ->call('selectTable', $table->id)
            ->assertSet('selectedTableId', $table->id)
            ->assertDispatched('open-modal', id: 'table-details')
            ->assertSeeHtml('id="table-details"');
    }

    /** Filament giữ luồng test mở bàn, thêm món và gửi bếp nhưng không thực hiện checkout. */
    public function test_page_methods_execute_the_filament_monitoring_workflow(): void
    {
        [$owner, $store] = $this->ownerAndStore();
        $table = DiningTable::query()
            ->where('store_id', $store->id)
            ->whereDoesntHave('sessions', fn ($query) => $query->where('status', TableSessionStatus::Open->value))
            ->firstOrFail();
        $product = Product::query()->where('store_id', $store->id)->where('is_active', true)->firstOrFail();
        $printer = Printer::query()
            ->where('store_id', $store->id)
            ->where('printer_type', PrinterType::Kitchen->value)
            ->where('is_active', true)
            ->firstOrFail();

        $this->actingAs($owner);
        Filament::setCurrentPanel('admin');
        Filament::setTenant($store, isQuiet: true);

        Livewire::test(TableMap::class)
            ->call('selectTable', $table->id)
            ->call('openTable')
            ->set('draftItems', [[
                'product_id' => $product->id,
                'quantity' => 2,
                'notes' => 'Ít đá',
            ]])
            ->call('addItems')
            ->assertSee($product->name)
            ->assertSee(number_format((float) $product->price * 2, 0, ',', '.').' đ')
            ->set('selectedKitchenPrinterId', $printer->id)
            ->call('createKitchenTicket')
            ->assertHasNoErrors();

        $session = TableSession::query()
            ->where('table_id', $table->id)
            ->latest('id')
            ->firstOrFail();
        $order = Order::query()->where('table_session_id', $session->id)->firstOrFail();

        $this->assertSame(TableSessionStatus::Open, $session->status);
        $this->assertSame(2, $order->items()->firstOrFail()->quantity);
        $this->assertSame(1, $order->printJobs()->where('print_type', PrintType::Kitchen->value)->count());
        $this->assertSame(0, $order->printJobs()->where('print_type', PrintType::Receipt->value)->count());
    }

    /** Middleware tenant phải chặn staff truy cập standalone Page của chi nhánh khác. */
    public function test_staff_cannot_open_the_page_for_another_store(): void
    {
        $staff = User::query()->where('email', 'staff.tranphu@example.com')->firstOrFail();
        $otherStore = Store::query()->whereKeyNot($staff->store_id)->firstOrFail();

        $this->actingAs($staff)
            ->get(TableMap::getUrl(panel: 'admin', tenant: $otherStore))
            ->assertNotFound();
    }

    /** Trả owner cùng một Store có đủ dữ liệu danh mục và máy in cho bài test. */
    private function ownerAndStore(): array
    {
        $owner = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $store = Store::query()
            ->whereHas('printers', fn ($query) => $query->where('printer_type', PrinterType::Kitchen->value))
            ->firstOrFail();

        return [$owner, $store];
    }
}
