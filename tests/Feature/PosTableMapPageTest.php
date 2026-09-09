<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PrinterType;
use App\Enums\TableSessionStatus;
use App\Filament\Pages\Pos\TableMap;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Payment;
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
            ->assertOk();

        Filament::setCurrentPanel('admin');
        Filament::setTenant($store, isQuiet: true);

        $component = Livewire::test(TableMap::class);
        $tableMap = $component->viewData('tableMap');

        $this->assertSame(DiningTable::query()->where('store_id', $store->id)->count(), $tableMap['statistics']['total']);
        $this->assertCount($tableMap['statistics']['total'], $tableMap['tables']);
        $this->assertNotEmpty($tableMap['catalog']);
        $this->assertNotEmpty($tableMap['kitchenPrinters']);
    }

    /** Các method Livewire phải chạy trọn luồng mở bàn, thêm món, in bếp và checkout. */
    public function test_page_methods_execute_the_complete_pos_workflow(): void
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
            ->set('selectedKitchenPrinterId', $printer->id)
            ->call('createKitchenTicket')
            ->call('checkout')
            ->assertHasNoErrors();

        $session = TableSession::query()
            ->where('table_id', $table->id)
            ->latest('id')
            ->firstOrFail();
        $order = Order::query()->where('table_session_id', $session->id)->firstOrFail();

        $this->assertSame(TableSessionStatus::Closed, $session->status);
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(2, $order->items()->firstOrFail()->quantity);
        $this->assertSame(1, $order->printJobs()->count());
        $this->assertSame(1, Payment::query()->where('order_id', $order->id)->count());
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
