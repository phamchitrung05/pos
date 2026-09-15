<?php

namespace Tests\Feature;

use App\Actions\Pos\AddOrderItems;
use App\Actions\Pos\CheckoutAndQueueReceipt;
use App\Actions\Pos\CheckoutTable;
use App\Actions\Pos\CreateKitchenPrintJob;
use App\Actions\Pos\CreateReceiptPrintJob;
use App\Actions\Pos\OpenTableSession;
use App\Actions\Pos\RetryPrintJob;
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
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Printer;
use App\Models\PrintJob;
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

    /** Thêm món dùng giá Product mặc định, gộp dòng trùng và tính lại tổng. */
    public function test_add_order_items_uses_server_price_and_recalculates_total(): void
    {
        $actor = $this->owner();
        $order = $this->openOrder($actor);
        $product = $this->productForOrder($order);

        $order = app(AddOrderItems::class)->handle($order, $actor, [[
            'product_id' => $product->id,
            'quantity' => 2,
            'notes' => 'Ít đá',
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

    /** Giá nhân viên nhập phải áp dụng cho dòng gộp và được dùng khi tính tổng order. */
    public function test_add_and_update_order_item_accept_an_authorized_custom_price(): void
    {
        $actor = $this->owner();
        $order = $this->openOrder($actor);
        $product = $this->productForOrder($order);

        $order = app(AddOrderItems::class)->handle($order, $actor, [[
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 25_000,
        ]]);
        $item = $order->items->firstOrFail();

        $this->assertSame('25000.00', $item->unit_price);
        $this->assertSame('50000.00', $order->total);

        $updated = app(UpdateOrderItem::class)->handle($item, $actor, 2, null, 30_000);
        $this->assertSame('30000.00', $updated->unit_price);
        $this->assertSame('60000.00', $updated->order->total);
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
        $this->assertSame(80, $firstJob->payload['document']['paper_width_mm']);
        $this->assertSame(576, $firstJob->payload['document']['dots_per_line']);
        $this->assertSame('raw', $firstJob->payload['document']['render_mode']);
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

    /** Dòng đã in vẫn có thể giảm số lượng do khách sửa yêu cầu, nhưng khóa ghi chú chế biến. */
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

        $reducedItem = app(UpdateOrderItem::class)->handle($updatedItem, $actor, 1, 'Không đường');
        $this->assertSame(1, $reducedItem->quantity);
        $this->assertSame(number_format((float) $product->price, 2, '.', ''), $reducedItem->order->total);

        $this->expectException(ValidationException::class);

        app(UpdateOrderItem::class)->handle($reducedItem, $actor, 1, 'Có đường');
    }

    /** Món đã lưu server nhưng chưa chuyển bếp vẫn có thể sửa từ ba phần xuống hai phần. */
    public function test_update_order_item_can_decrease_unprinted_quantity(): void
    {
        $actor = $this->owner();
        $order = $this->openOrder($actor);
        $product = $this->productForOrder($order);
        $order = app(AddOrderItems::class)->handle($order, $actor, [[
            'product_id' => $product->id,
            'quantity' => 3,
        ]]);

        $updatedItem = app(UpdateOrderItem::class)->handle($order->items->firstOrFail(), $actor, 2);

        $this->assertSame(2, $updatedItem->quantity);
        $this->assertSame(''.((int) $product->price * 2).'.00', $updatedItem->order->total);
    }

    /** Dòng món đã gửi bếp về 0 phải biến mất khỏi order và tổng tiền phải được tính lại. */
    public function test_update_order_item_can_remove_sent_item_at_zero(): void
    {
        $actor = $this->owner();
        $order = $this->openOrder($actor);
        $product = $this->productForOrder($order);
        $order = app(AddOrderItems::class)->handle($order, $actor, [[
            'product_id' => $product->id,
            'quantity' => 1,
            'notes' => 'Không đá',
        ]]);
        $item = $order->items->firstOrFail();

        app(CreateKitchenPrintJob::class)->handle($order, $this->kitchenPrinterForOrder($order), $actor);

        $removedItem = app(UpdateOrderItem::class)->handle($item->refresh(), $actor, 0, 'Không đá');

        $this->assertFalse(OrderItem::query()->whereKey($item->getKey())->exists());
        $this->assertSame(0, $order->items()->count());
        $this->assertSame('0.00', $removedItem->order->total);
        $this->assertSame(OrderStatus::Cancelled, $removedItem->order->status);
        $this->assertSame(TableSessionStatus::Cancelled, $removedItem->order->tableSession->status);
        $this->assertNotNull($removedItem->order->tableSession->end_time);
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

    /** Hóa đơn dùng snapshot payment, đúng profile giấy và chỉ tạo một job cho mỗi giao dịch. */
    public function test_receipt_print_job_is_idempotent_and_contains_the_render_profile(): void
    {
        $actor = $this->owner();
        $order = $this->openOrder($actor);
        $product = $this->productForOrder($order);
        $order = app(AddOrderItems::class)->handle($order, $actor, [[
            'product_id' => $product->id,
            'quantity' => 2,
        ]]);
        app(CreateKitchenPrintJob::class)->handle($order, $this->kitchenPrinterForOrder($order), $actor);
        $payment = app(CheckoutTable::class)->handle($order, $actor);
        $receiptPrinter = Printer::query()
            ->where('store_id', $order->store_id)
            ->where('printer_type', PrinterType::Receipt->value)
            ->where('is_active', true)
            ->firstOrFail();

        $firstJob = app(CreateReceiptPrintJob::class)->handle($payment, $receiptPrinter, $actor);
        $retriedJob = app(CreateReceiptPrintJob::class)->handle($payment->refresh(), $receiptPrinter, $actor);

        $this->assertSame($firstJob->id, $retriedJob->id);
        $this->assertSame(PrintType::Receipt, $firstJob->print_type);
        $this->assertSame(80, $firstJob->payload['document']['paper_width_mm']);
        $this->assertSame(576, $firstJob->payload['document']['dots_per_line']);
        $this->assertSame('vi-VN', $firstJob->payload['document']['locale']);
        $this->assertSame('raw', $firstJob->payload['document']['render_mode']);
        $this->assertSame((int) $payment->amount, $firstJob->payload['payment']['amount']);
        $this->assertSame($product->name, $firstJob->payload['order']['items'][0]['name']);
    }

    /** Checkout orchestration phải trả Payment và receipt job trong hai bước tách biệt. */
    public function test_checkout_orchestration_queues_a_receipt_after_payment_completes(): void
    {
        $actor = $this->owner();
        $order = $this->openOrder($actor);
        $product = $this->productForOrder($order);
        $order = app(AddOrderItems::class)->handle($order, $actor, [[
            'product_id' => $product->id,
            'quantity' => 1,
        ]]);
        app(CreateKitchenPrintJob::class)->handle($order, $this->kitchenPrinterForOrder($order), $actor);
        $receiptPrinter = $this->receiptPrinterForOrder($order);

        $result = app(CheckoutAndQueueReceipt::class)->handle(
            $order,
            $actor,
            $receiptPrinter,
            clientRequestId: (string) Str::uuid(),
        );

        $this->assertSame(PaymentStatus::Completed, $result['payment']->status);
        $this->assertSame(PrintType::Receipt, $result['receiptPrintJob']?->print_type);
        $this->assertNull($result['receiptError']);
        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
    }

    /** Lỗi cấu hình receipt không được rollback Payment hoặc mở lại bàn. */
    public function test_receipt_queue_failure_does_not_rollback_a_completed_payment(): void
    {
        $actor = $this->owner();
        $order = $this->openOrder($actor);
        $product = $this->productForOrder($order);
        $order = app(AddOrderItems::class)->handle($order, $actor, [[
            'product_id' => $product->id,
            'quantity' => 1,
        ]]);
        app(CreateKitchenPrintJob::class)->handle($order, $this->kitchenPrinterForOrder($order), $actor);
        $receiptPrinter = $this->receiptPrinterForOrder($order);
        $receiptPrinter->update(['is_active' => false]);

        $result = app(CheckoutAndQueueReceipt::class)->handle(
            $order,
            $actor,
            $receiptPrinter,
            clientRequestId: (string) Str::uuid(),
        );

        $this->assertSame(PaymentStatus::Completed, $result['payment']->status);
        $this->assertNull($result['receiptPrintJob']);
        $this->assertSame('Máy in hóa đơn không hợp lệ cho cửa hàng này.', $result['receiptError']);
        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
        $this->assertSame(TableSessionStatus::Closed, $order->tableSession->refresh()->status);
        $this->assertSame(0, $order->printJobs()->where('print_type', PrintType::Receipt->value)->count());
    }

    /** Hóa đơn đã in được xếp hàng lại bằng chính snapshot mà không tạo Payment mới. */
    public function test_printed_receipt_can_be_reprinted_with_the_original_snapshot(): void
    {
        $actor = $this->owner();
        $order = $this->openOrder($actor);
        $product = $this->productForOrder($order);
        $order = app(AddOrderItems::class)->handle($order, $actor, [[
            'product_id' => $product->id,
            'quantity' => 1,
        ]]);
        app(CreateKitchenPrintJob::class)->handle($order, $this->kitchenPrinterForOrder($order), $actor);
        $payment = app(CheckoutTable::class)->handle($order, $actor);
        $job = app(CreateReceiptPrintJob::class)->handle($payment, $this->receiptPrinterForOrder($order), $actor);
        $job->forceFill([
            'status' => PrintJobStatus::Printed,
            'attempts' => 1,
            'printed_at' => now(),
        ])->save();
        $originalPayload = $job->payload;

        $retriedJob = app(RetryPrintJob::class)->handle($job, $actor);

        $this->assertSame(PrintJobStatus::Pending, $retriedJob->status);
        $this->assertNull($retriedJob->printed_at);
        $this->assertSame(1, $retriedJob->attempts);
        $this->assertSame($originalPayload, $retriedJob->payload);
        $this->assertSame(1, Payment::query()->where('order_id', $order->id)->count());
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

    /** Owner được sửa dữ liệu đối soát; staff vẫn phải thao tác qua luồng POS chuyên trách. */
    public function test_only_owner_can_edit_filament_transaction_resources(): void
    {
        $records = [
            TableSessionResource::class => TableSession::query()->firstOrFail(),
            OrderResource::class => Order::query()->firstOrFail(),
            OrderItemResource::class => OrderItem::query()->firstOrFail(),
            PaymentResource::class => Payment::query()->firstOrFail(),
            PrintJobResource::class => PrintJob::query()->firstOrFail(),
        ];

        $this->actingAs($this->owner());
        foreach ($records as $resource => $record) {
            $this->assertTrue($resource::canEdit($record));
        }

        $staff = User::query()->where('email', 'staff.tranphu@example.com')->firstOrFail();
        $this->actingAs($staff);
        foreach ($records as $resource => $record) {
            $this->assertFalse($resource::canEdit($record));
        }
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

    /** Chọn máy in hóa đơn đang hoạt động thuộc đúng tenant của order. */
    private function receiptPrinterForOrder(Order $order): Printer
    {
        return Printer::query()
            ->where('store_id', $order->store_id)
            ->where('printer_type', PrinterType::Receipt->value)
            ->where('is_active', true)
            ->firstOrFail();
    }
}
