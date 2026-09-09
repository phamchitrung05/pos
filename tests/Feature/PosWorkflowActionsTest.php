<?php

namespace Tests\Feature;

use App\Actions\Pos\AddOrderItems;
use App\Actions\Pos\CheckoutTable;
use App\Actions\Pos\CreateKitchenPrintJob;
use App\Actions\Pos\OpenTableSession;
use App\Actions\Pos\UpdateOrderItem;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PrinterType;
use App\Enums\PrintJobStatus;
use App\Enums\PrintType;
use App\Enums\TableSessionStatus;
use App\Filament\Resources\OrderItems\OrderItemResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\PrintJobs\PrintJobResource;
use App\Filament\Resources\TableSessions\TableSessionResource;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Printer;
use App\Models\Product;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Kiểm tra lớp nghiệp vụ POS độc lập với giao diện Filament và API.
 *
 * Các test dùng dữ liệu seeder thật để đồng thời xác nhận action tuân thủ
 * ranh giới tenant, role/permission và các quan hệ hiện có của dự án.
 */
class PosWorkflowActionsTest extends TestCase
{
    use RefreshDatabase;

    /** Khởi tạo đầy đủ hai chi nhánh, quyền và dữ liệu tham chiếu trước mỗi test. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /** Hai thiết bị mở cùng bàn phải nhận cùng một phiên/order thay vì tạo dữ liệu trùng. */
    public function test_open_table_session_creates_one_order_and_reuses_it_for_another_device(): void
    {
        $actor = $this->owner();
        $table = $this->availableTable();

        $session = app(OpenTableSession::class)->handle($table, $actor);
        $order = $session->order;

        $this->assertSame(TableSessionStatus::Open, $session->status);
        $this->assertSame($actor->id, $session->opened_by);
        $this->assertNull($session->end_time);
        $this->assertSame(OrderStatus::Open, $order->status);
        $this->assertSame($actor->id, $order->created_by);
        $this->assertSame('0.00', $order->total);

        $sameSession = app(OpenTableSession::class)->handle($table, $actor);

        $this->assertSame($session->id, $sameSession->id);
        $this->assertSame($order->id, $sameSession->order->id);
        $this->assertSame(1, TableSession::query()->where('active_table_id', $table->id)->count());
        $this->assertSame(1, Order::query()->where('table_session_id', $session->id)->count());
    }

    /** Thêm món phải dùng giá Product tại server, gộp dòng trùng và tính lại tổng. */
    public function test_add_order_items_uses_server_price_and_recalculates_total(): void
    {
        $actor = $this->owner();
        $order = $this->openOrder($actor);
        $product = $this->productForOrder($order);

        $order = app(AddOrderItems::class)->handle($order, $actor, [[
            'product_id' => $product->id,
            'quantity' => 2,
            'notes' => 'Ít đá',
            // Giá giả này không nằm trong rule validate và tuyệt đối không được action sử dụng.
            'unit_price' => 1,
        ]]);

        $item = $order->items->firstOrFail();

        $this->assertSame($product->price, $item->unit_price);
        $this->assertSame(2, $item->quantity);
        $this->assertSame(number_format((float) $product->price * 2, 2, '.', ''), $order->total);

        // Thêm cùng sản phẩm và ghi chú phải tăng số lượng trên dòng cũ, không tạo dòng trùng.
        $order = app(AddOrderItems::class)->handle($order, $actor, [[
            'product_id' => $product->id,
            'quantity' => 1,
            'notes' => 'Ít đá',
        ]]);

        $this->assertCount(1, $order->items);
        $this->assertSame(3, $order->items->first()->quantity);
        $this->assertSame(number_format((float) $product->price * 3, 2, '.', ''), $order->total);
    }

    /** Phiếu bếp lần sau chỉ chứa số lượng tăng thêm và không thể tạo khi không có món mới. */
    public function test_kitchen_print_job_only_contains_unprinted_quantity(): void
    {
        $actor = $this->owner();
        $order = $this->openOrder($actor);
        $product = $this->productForOrder($order);
        $printer = $this->kitchenPrinterForOrder($order);

        $order = app(AddOrderItems::class)->handle($order, $actor, [[
            'product_id' => $product->id,
            'quantity' => 2,
        ]]);

        $firstJob = app(CreateKitchenPrintJob::class)->handle($order, $printer, $actor);

        $this->assertSame(PrintType::Kitchen, $firstJob->print_type);
        $this->assertSame(PrintJobStatus::Pending, $firstJob->status);
        $this->assertSame(2, $firstJob->payload['items'][0]['quantity']);
        $this->assertSame(2, $order->items->first()->refresh()->kitchen_printed_quantity);

        // Tăng thêm một món trên dòng đã in; snapshot thứ hai chỉ được chứa phần chênh lệch là một.
        $order = app(AddOrderItems::class)->handle($order->refresh(), $actor, [[
            'product_id' => $product->id,
            'quantity' => 1,
        ]]);
        $secondJob = app(CreateKitchenPrintJob::class)->handle($order, $printer, $actor);

        $this->assertSame(1, $secondJob->payload['items'][0]['quantity']);
        $this->assertSame(3, $order->items->first()->refresh()->kitchen_printed_quantity);

        $this->expectException(ValidationException::class);

        app(CreateKitchenPrintJob::class)->handle($order->refresh(), $printer, $actor);
    }

    /** Dòng đã in có thể tăng số lượng nhưng không được giảm hoặc đổi ghi chú đã gửi cho bếp. */
    public function test_update_order_item_protects_information_already_sent_to_kitchen(): void
    {
        $actor = $this->owner();
        $order = $this->openOrder($actor);
        $product = $this->productForOrder($order);

        $order = app(AddOrderItems::class)->handle($order, $actor, [[
            'product_id' => $product->id,
            'quantity' => 2,
            'notes' => 'Không đường',
        ]]);
        $item = $order->items->firstOrFail();

        app(CreateKitchenPrintJob::class)->handle($order, $this->kitchenPrinterForOrder($order), $actor);

        $updatedItem = app(UpdateOrderItem::class)->handle($item->refresh(), $actor, 3, 'Không đường');

        $this->assertSame(3, $updatedItem->quantity);
        $this->assertSame(number_format((float) $product->price * 3, 2, '.', ''), $updatedItem->order->total);

        $this->expectException(ValidationException::class);

        app(UpdateOrderItem::class)->handle($updatedItem, $actor, 1, 'Không đường');
    }

    /** Checkout phải lấy tổng server, tạo đúng một payment rồi đóng order và phiên bàn. */
    public function test_checkout_is_idempotent_and_closes_the_table_session(): void
    {
        $actor = $this->owner();
        $order = $this->openOrder($actor);
        $product = $this->productForOrder($order);
        $requestId = (string) Str::uuid();

        $order = app(AddOrderItems::class)->handle($order, $actor, [[
            'product_id' => $product->id,
            'quantity' => 2,
        ]]);

        // Checkout chỉ được thực hiện sau khi toàn bộ món đã có snapshot phiếu bếp.
        app(CreateKitchenPrintJob::class)->handle($order, $this->kitchenPrinterForOrder($order), $actor);

        $firstPayment = app(CheckoutTable::class)->handle($order, $actor, PaymentMethod::Cash, $requestId);
        $retriedPayment = app(CheckoutTable::class)->handle($order->refresh(), $actor, PaymentMethod::Cash, $requestId);

        $this->assertSame($firstPayment->id, $retriedPayment->id);
        $this->assertSame(1, Payment::query()->where('client_request_id', $requestId)->count());
        $this->assertSame($order->total, $firstPayment->amount);
        $this->assertSame(PaymentMethod::Cash, $firstPayment->payment_method);
        $this->assertSame(PaymentStatus::Completed, $firstPayment->status);
        $this->assertSame($actor->id, $firstPayment->received_by);
        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);

        $session = $order->tableSession->refresh();

        $this->assertSame(TableSessionStatus::Closed, $session->status);
        $this->assertSame($actor->id, $session->closed_by);
        $this->assertNotNull($session->end_time);
    }

    /** Checkout phải dừng nếu còn món mới chưa được đưa vào bất kỳ phiếu bếp nào. */
    public function test_checkout_rejects_an_order_with_unprinted_kitchen_items(): void
    {
        $actor = $this->owner();
        $order = $this->openOrder($actor);
        $product = $this->productForOrder($order);

        $order = app(AddOrderItems::class)->handle($order, $actor, [[
            'product_id' => $product->id,
            'quantity' => 1,
        ]]);

        $this->expectException(ValidationException::class);

        app(CheckoutTable::class)->handle($order, $actor);
    }

    /** Action phải dùng Model Policy để staff không thể thao tác trên bàn của chi nhánh khác. */
    public function test_staff_cannot_open_a_table_from_another_store(): void
    {
        $staff = User::query()->where('email', 'staff.tranphu@example.com')->firstOrFail();
        $foreignTable = DiningTable::query()
            ->where('store_id', '!=', $staff->store_id)
            ->firstOrFail();

        $this->expectException(AuthorizationException::class);

        app(OpenTableSession::class)->handle($foreignTable, $staff);
    }

    /** Model phải tự áp dụng status open và sentinel kể cả khi caller không truyền status. */
    public function test_table_session_default_status_still_reserves_the_table(): void
    {
        $table = $this->availableTable();

        $session = TableSession::create([
            'table_id' => $table->id,
            'start_time' => now(),
        ]);

        $this->assertSame(TableSessionStatus::Open, $session->status);
        $this->assertSame($table->id, $session->active_table_id);
    }

    /** Các Resource giao dịch chỉ dùng để đối soát, không được phép bỏ qua action bằng CRUD. */
    public function test_filament_transaction_resources_are_read_only(): void
    {
        $this->assertFalse(TableSessionResource::canCreate());
        $this->assertFalse(OrderResource::canCreate());
        $this->assertFalse(OrderItemResource::canCreate());
        $this->assertFalse(PaymentResource::canCreate());
        $this->assertFalse(PrintJobResource::canCreate());
    }

    /** Lấy tài khoản owner do seeder cấp toàn bộ quyền để tập trung test invariant nghiệp vụ. */
    private function owner(): User
    {
        return User::query()->where('email', 'owner@example.com')->firstOrFail();
    }

    /** Chọn bàn đã có lịch sử đóng nhưng hiện không còn phiên open. */
    private function availableTable(): DiningTable
    {
        return DiningTable::query()
            ->whereDoesntHave('sessions', fn ($query) => $query->where('status', TableSessionStatus::Open->value))
            ->firstOrFail();
    }

    /** Mở bàn và trả về order chính vừa được action tạo. */
    private function openOrder(User $actor): Order
    {
        $session = app(OpenTableSession::class)->handle($this->availableTable(), $actor);

        return $session->order;
    }

    /** Chọn sản phẩm đang bán thuộc đúng tenant của order. */
    private function productForOrder(Order $order): Product
    {
        return Product::query()
            ->where('store_id', $order->store_id)
            ->where('is_active', true)
            ->firstOrFail();
    }

    /** Chọn máy in bếp đang hoạt động thuộc đúng tenant của order. */
    private function kitchenPrinterForOrder(Order $order): Printer
    {
        return Printer::query()
            ->where('store_id', $order->store_id)
            ->where('printer_type', PrinterType::Kitchen->value)
            ->where('is_active', true)
            ->firstOrFail();
    }
}
