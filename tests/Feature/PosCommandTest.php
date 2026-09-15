<?php

namespace Tests\Feature;

use App\Actions\Pos\ProcessPosCommand;
use App\Enums\OrderStatus;
use App\Enums\PosCommandStatus;
use App\Enums\PosCommandType;
use App\Enums\PrinterType;
use App\Enums\PrintType;
use App\Enums\TableSessionStatus;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PosCommand;
use App\Models\Printer;
use App\Models\Product;
use App\Models\Store;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Kiểm tra command journal chống thực thi lặp cho toàn bộ luồng ghi POS. */
class PosCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /** Cùng UUID phải trả result cũ và không lặp tác động của năm loại command. */
    public function test_all_pos_commands_return_the_stored_result_when_retried(): void
    {
        [$actor, $store] = $this->staffAndStore();
        $deviceId = (string) Str::uuid();
        $table = $this->availableTable($store);
        $processor = app(ProcessPosCommand::class);

        $openId = (string) Str::uuid();
        $openPayload = ['table_id' => $table->id];
        $opened = $processor->handle($openId, $store, $deviceId, $actor, PosCommandType::OpenTable, $openPayload);
        $openedAgain = $processor->handle($openId, $store, $deviceId, $actor, PosCommandType::OpenTable, $openPayload);

        $this->assertSame(PosCommandStatus::Completed, $opened->status);
        $this->assertSame($opened->result, $openedAgain->result);
        $this->assertSame(1, $openedAgain->attempts);
        $this->assertSame(1, TableSession::query()->where('table_id', $table->id)->where('status', TableSessionStatus::Open->value)->count());
        $order = Order::query()->findOrFail($opened->result['order_id']);
        $product = Product::query()->where('store_id', $store->id)->where('is_active', true)->firstOrFail();

        $addId = (string) Str::uuid();
        $addPayload = [
            'order_id' => $order->id,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'notes' => 'Ít đá',
            ]],
        ];
        $added = $processor->handle($addId, $store, $deviceId, $actor, PosCommandType::AddOrderItems, $addPayload);
        // Thứ tự key khác nhau vẫn là cùng payload sau khi canonicalize.
        $addedAgain = $processor->handle($addId, $store, $deviceId, $actor, PosCommandType::AddOrderItems, [
            'items' => [[
                'notes' => 'Ít đá',
                'quantity' => 2,
                'product_id' => $product->id,
            ]],
            'order_id' => $order->id,
        ]);

        $this->assertSame($added->result, $addedAgain->result);
        $this->assertSame(2, $order->items()->firstOrFail()->quantity);

        $item = $order->items()->firstOrFail();
        $updateId = (string) Str::uuid();
        $updatePayload = ['order_item_id' => $item->id, 'quantity' => 3, 'notes' => 'Ít đá'];
        $updated = $processor->handle($updateId, $store, $deviceId, $actor, PosCommandType::UpdateOrderItem, $updatePayload);
        $updatedAgain = $processor->handle($updateId, $store, $deviceId, $actor, PosCommandType::UpdateOrderItem, $updatePayload);

        $this->assertSame($updated->result, $updatedAgain->result);
        $this->assertSame(3, $item->refresh()->quantity);

        $kitchenPrinter = $this->printer($store, PrinterType::Kitchen);
        $kitchenId = (string) Str::uuid();
        $kitchenPayload = ['order_id' => $order->id, 'printer_id' => $kitchenPrinter->id];
        $ticket = $processor->handle($kitchenId, $store, $deviceId, $actor, PosCommandType::CreateKitchenTicket, $kitchenPayload);
        $ticketAgain = $processor->handle($kitchenId, $store, $deviceId, $actor, PosCommandType::CreateKitchenTicket, $kitchenPayload);

        $this->assertSame($ticket->result, $ticketAgain->result);
        $this->assertSame(1, $order->printJobs()->where('print_type', PrintType::Kitchen->value)->count());

        $receiptPrinter = $this->printer($store, PrinterType::Receipt);
        $checkoutId = (string) Str::uuid();
        $checkoutPayload = [
            'order_id' => $order->id,
            'receipt_printer_id' => $receiptPrinter->id,
            'payment_method' => 'cash',
        ];
        $checkout = $processor->handle($checkoutId, $store, $deviceId, $actor, PosCommandType::Checkout, $checkoutPayload);
        $checkoutAgain = $processor->handle($checkoutId, $store, $deviceId, $actor, PosCommandType::Checkout, $checkoutPayload);

        $this->assertSame(PosCommandStatus::Completed, $checkout->status);
        $this->assertSame($checkout->result, $checkoutAgain->result);
        $this->assertSame(1, Payment::query()->where('client_request_id', $checkoutId)->count());
        $this->assertSame(1, $order->printJobs()->where('print_type', PrintType::Receipt->value)->count());
        $this->assertSame(5, PosCommand::query()->where('device_id', $deviceId)->count());
    }

    /** Update qua command với quantity 0 phải xóa món cuối và hủy phiên bàn, đồng thời vẫn idempotent. */
    public function test_update_item_command_can_remove_the_last_item(): void
    {
        [$actor, $store] = $this->staffAndStore();
        $deviceId = (string) Str::uuid();
        $order = $this->openOrderThroughCommand($actor, $store, $deviceId);
        $product = Product::query()->where('store_id', $store->id)->where('is_active', true)->firstOrFail();
        $processor = app(ProcessPosCommand::class);

        $processor->handle((string) Str::uuid(), $store, $deviceId, $actor, PosCommandType::AddOrderItems, [
            'order_id' => $order->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);
        $item = $order->items()->firstOrFail();
        $commandId = (string) Str::uuid();
        $payload = ['order_item_id' => $item->id, 'quantity' => 0, 'notes' => null];

        $removed = $processor->handle($commandId, $store, $deviceId, $actor, PosCommandType::UpdateOrderItem, $payload);
        $removedAgain = $processor->handle($commandId, $store, $deviceId, $actor, PosCommandType::UpdateOrderItem, $payload);

        $this->assertSame(PosCommandStatus::Completed, $removed->status);
        $this->assertSame($removed->result, $removedAgain->result);
        $this->assertDatabaseMissing('order_items', ['id' => $item->id]);
        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(TableSessionStatus::Cancelled, $order->tableSession->refresh()->status);
        $this->assertSame(1, PosCommand::query()->whereKey($commandId)->value('attempts'));
    }

    /** Cùng UUID nhưng payload khác phải bị chặn thay vì thực hiện yêu cầu mới. */
    public function test_command_id_cannot_be_reused_with_a_different_payload(): void
    {
        [$actor, $store] = $this->staffAndStore();
        $deviceId = (string) Str::uuid();
        $order = $this->openOrderThroughCommand($actor, $store, $deviceId);
        $product = Product::query()->where('store_id', $store->id)->where('is_active', true)->firstOrFail();
        $commandId = (string) Str::uuid();
        $processor = app(ProcessPosCommand::class);

        $processor->handle($commandId, $store, $deviceId, $actor, PosCommandType::AddOrderItems, [
            'order_id' => $order->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $this->expectException(ValidationException::class);

        $processor->handle($commandId, $store, $deviceId, $actor, PosCommandType::AddOrderItems, [
            'order_id' => $order->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);
    }

    /** Phiên nháp được đồng bộ muộn vẫn phải tính thời gian từ lúc nhân viên chọn món đầu tiên. */
    public function test_open_table_preserves_the_local_draft_start_time(): void
    {
        [$actor, $store] = $this->staffAndStore();
        $startedAt = now()->subMinutes(12)->startOfSecond();

        $command = app(ProcessPosCommand::class)->handle(
            (string) Str::uuid(),
            $store,
            (string) Str::uuid(),
            $actor,
            PosCommandType::OpenTable,
            [
                'table_id' => $this->availableTable($store)->id,
                'started_at' => $startedAt->toISOString(),
            ],
        );

        $session = TableSession::query()->findOrFail($command->result['table_session_id']);
        $this->assertTrue($session->start_time->equalTo($startedAt));
    }

    /** POS được phép nhập bill muộn rồi thanh toán ngay mà không tạo phiếu bếp không còn giá trị. */
    public function test_checkout_command_can_complete_a_late_bill_with_unprinted_items(): void
    {
        [$actor, $store] = $this->staffAndStore();
        $deviceId = (string) Str::uuid();
        $order = $this->openOrderThroughCommand($actor, $store, $deviceId);
        $product = Product::query()->where('store_id', $store->id)->where('is_active', true)->firstOrFail();
        $processor = app(ProcessPosCommand::class);

        $processor->handle((string) Str::uuid(), $store, $deviceId, $actor, PosCommandType::AddOrderItems, [
            'order_id' => $order->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);
        $checkout = $processor->handle((string) Str::uuid(), $store, $deviceId, $actor, PosCommandType::Checkout, [
            'order_id' => $order->id,
            'receipt_printer_id' => null,
            'payment_method' => 'cash',
            'allow_unprinted_kitchen_items' => true,
        ]);

        $this->assertSame(PosCommandStatus::Completed, $checkout->status);
        $this->assertSame('paid', $order->refresh()->status->value);
        $this->assertSame(TableSessionStatus::Closed, $order->tableSession->refresh()->status);
    }

    /** Command lỗi là kết quả terminal; gửi lại cùng UUID không tăng attempts hoặc chạy lại. */
    public function test_failed_command_is_returned_without_being_executed_again(): void
    {
        [$actor, $store] = $this->staffAndStore();
        $deviceId = (string) Str::uuid();
        $commandId = (string) Str::uuid();
        $processor = app(ProcessPosCommand::class);
        $payload = ['table_id' => PHP_INT_MAX];

        $failed = $processor->handle($commandId, $store, $deviceId, $actor, PosCommandType::OpenTable, $payload);
        $failedAgain = $processor->handle($commandId, $store, $deviceId, $actor, PosCommandType::OpenTable, $payload);

        $this->assertSame(PosCommandStatus::Failed, $failed->status);
        $this->assertSame('Không tìm thấy dữ liệu thuộc cửa hàng cho thao tác này.', $failed->error);
        $this->assertSame($failed->error, $failedAgain->error);
        $this->assertSame(1, $failedAgain->attempts);
        $this->assertNotNull($failedAgain->processed_at);
    }

    /** Staff không thể ghi command vào tenant khác và UUID không bị chiếm khi bị từ chối. */
    public function test_staff_cannot_create_a_command_for_another_store(): void
    {
        [$actor, $store] = $this->staffAndStore();
        $otherStore = Store::query()->whereKeyNot($store->id)->firstOrFail();
        $commandId = (string) Str::uuid();

        try {
            app(ProcessPosCommand::class)->handle(
                $commandId,
                $otherStore,
                (string) Str::uuid(),
                $actor,
                PosCommandType::OpenTable,
                ['table_id' => 1],
            );
            $this->fail('Command chéo tenant phải bị từ chối.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('pos_commands', ['id' => $commandId]);
        }
    }

    /** @return array{User, Store} */
    private function staffAndStore(): array
    {
        $actor = User::query()->where('email', 'staff.tranphu@example.com')->firstOrFail();

        return [$actor, $actor->store];
    }

    private function availableTable(Store $store): DiningTable
    {
        return DiningTable::query()
            ->where('store_id', $store->id)
            ->whereDoesntHave('sessions', fn ($query) => $query->where('status', TableSessionStatus::Open->value))
            ->firstOrFail();
    }

    private function printer(Store $store, PrinterType $type): Printer
    {
        return Printer::query()
            ->where('store_id', $store->id)
            ->where('printer_type', $type->value)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function openOrderThroughCommand(User $actor, Store $store, string $deviceId): Order
    {
        $command = app(ProcessPosCommand::class)->handle(
            (string) Str::uuid(),
            $store,
            $deviceId,
            $actor,
            PosCommandType::OpenTable,
            ['table_id' => $this->availableTable($store)->id],
        );

        return Order::query()->findOrFail($command->result['order_id']);
    }
}
