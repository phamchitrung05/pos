<?php

namespace Tests\Feature;

use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Store;
use App\Models\TableSession;
use App\Models\TableZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Bảo vệ số lượng fake data và cấu hình role/permission mà môi trường phát triển cần. */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    /** Seeder phải tạo đúng số lượng và mọi model nghiệp vụ phải có Store trực tiếp. */
    public function test_database_seeder_creates_complete_tenant_data(): void
    {
        $this->seed();

        $this->assertSame(2, Store::query()->count());
        $this->assertSame(2, User::query()->count());
        $this->assertSame(10, TableZone::query()->count());
        $this->assertSame(10, DiningTable::query()->count());
        $this->assertSame(10, TableSession::query()->count());
        $this->assertSame(10, ProductGroup::query()->count());
        $this->assertSame(10, Product::query()->count());
        $this->assertSame(10, Order::query()->count());
        $this->assertSame(10, OrderItem::query()->count());
        $this->assertSame(10, Payment::query()->count());
        $this->assertSame(10, Printer::query()->count());
        $this->assertSame(10, PrintJob::query()->count());

        // Sáu model được bổ sung store_id phải không có bản ghi mồ côi để
        // Filament và Model Policies luôn xác định được tenant sở hữu.
        foreach ([TableSession::class, Product::class, Order::class, OrderItem::class, Payment::class, PrintJob::class] as $model) {
            $this->assertSame(0, $model::query()->whereNull('store_id')->count());
        }

        $this->assertSame(72, Permission::query()->count());
        $this->assertSame(72, Role::findByName('owner')->permissions()->count());
        $this->assertSame(60, Role::findByName('staff')->permissions()->count());
    }
}
