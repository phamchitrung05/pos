<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PosCommandStatus;
use App\Enums\PrinterType;
use App\Enums\PrintJobStatus;
use App\Enums\PrintType;
use App\Enums\TableSessionStatus;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\Product;
use App\Models\Store;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Kiểm tra các API read, command, sync và callback in trong đúng tenant. */
class PosApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private string $deviceId;

    private string $token;

    private User $staff;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->staff = User::query()->where('email', 'staff.tranphu@example.com')->firstOrFail();
        $this->store = $this->staff->store;
        $this->deviceId = (string) Str::uuid();
        $this->token = $this->postJson(route('api.pos.login'), [
            'email' => $this->staff->email,
            'password' => 'password',
            'device_id' => $this->deviceId,
            'device_name' => 'POS test',
        ])->assertOk()->json('data.access_token');
    }

    public function test_bootstrap_tables_and_order_endpoints_only_return_the_authenticated_store(): void
    {
        $foreignTable = DiningTable::query()->where('store_id', '!=', $this->store->id)->firstOrFail();
        $foreignTable->update(['name' => 'Bàn tenant khác duy nhất']);
        $ownOrder = Order::query()->where('store_id', $this->store->id)->firstOrFail();
        $foreignOrder = Order::query()->where('store_id', '!=', $this->store->id)->firstOrFail();
        $ownTable = $ownOrder->tableSession->table;

        $this->api()->getJson(route('api.pos.bootstrap'))
            ->assertOk()
            ->assertJsonPath('data.store.id', $this->store->id)
            ->assertJsonPath('data.user.id', $this->staff->id)
            ->assertJsonCount($this->store->printers()->where('is_active', true)->count(), 'data.printers');

        $this->api()->getJson(route('api.pos.tables'))
            ->assertOk()
            ->assertJsonPath('data.statistics.total', $this->store->diningTables()->count())
            ->assertJsonMissing(['name' => 'Bàn tenant khác duy nhất']);

        $this->api()->getJson(route('api.pos.orders.show', $ownOrder->id))
            ->assertOk()
            ->assertJsonPath('data.id', $ownOrder->id)
            ->assertJsonPath('data.total', (int) $ownOrder->total);

        $this->api()->getJson(route('api.pos.orders.show', $foreignOrder->id))->assertNotFound();
        $this->api()->getJson(route('api.pos.tables.show', $ownTable->id))
            ->assertOk()
            ->assertJsonPath('data.id', $ownTable->id);
        $this->api()->getJson(route('api.pos.tables.show', $foreignOrder->tableSession->table_id))->assertNotFound();
    }

    public function test_order_history_only_returns_paid_and_closed_orders_completed_today_for_the_authenticated_store(): void
    {
        $ownOrders = Order::query()->where('store_id', $this->store->id)->take(3)->get();
        $todayOrder = $ownOrders[0];
        $yesterdayOrder = $ownOrders[1];
        $openOrder = $ownOrders[2];
        $foreignOrder = Order::query()->where('store_id', '!=', $this->store->id)->firstOrFail();
        $todayOrder->payments()->firstOrFail()->forceFill(['paid_at' => now()])->saveQuietly();
        $yesterdayOrder->payments()->firstOrFail()->forceFill(['paid_at' => now()->subDay()])->saveQuietly();
        $openOrder->payments()->firstOrFail()->forceFill(['paid_at' => now()])->saveQuietly();
        $openOrder->forceFill(['status' => OrderStatus::Open])->saveQuietly();
        $foreignOrder->payments()->firstOrFail()->forceFill(['paid_at' => now()])->saveQuietly();

        $response = $this->api()->getJson(route('api.pos.orders.index'))
            ->assertOk()
            ->assertJsonPath('data.date', today()->toDateString())
            ->assertJsonStructure([
                'data' => [
                    'date',
                    'orders' => [['id', 'code', 'tableName', 'timeLabel', 'status', 'statusLabel', 'total', 'items']],
                ],
            ]);

        $orderIds = collect($response->json('data.orders'))->pluck('id');
        $this->assertContains($todayOrder->id, $orderIds);
        $this->assertNotContains($yesterdayOrder->id, $orderIds);
        $this->assertNotContains($openOrder->id, $orderIds);
        $this->assertNotContains($foreignOrder->id, $orderIds);
        $this->assertSame([OrderStatus::Paid->value], $orderIds->isEmpty()
            ? []
            : collect($response->json('data.orders'))->pluck('status')->unique()->values()->all());
    }

    public function test_command_endpoint_executes_once_and_rejects_a_forged_device_id(): void
    {
        $table = $this->availableTable();
        $command = [
            'id' => (string) Str::uuid(),
            'device_id' => $this->deviceId,
            'type' => 'open_table',
            'payload' => ['table_id' => $table->id],
        ];

        $first = $this->api()->postJson(route('api.pos.commands.store'), $command)
            ->assertOk()
            ->assertJsonPath('data.status', PosCommandStatus::Completed->value)
            ->assertJsonPath('data.attempts', 1);
        $second = $this->api()->postJson(route('api.pos.commands.store'), $command)
            ->assertOk()
            ->assertJsonPath('data.attempts', 1);

        $this->assertSame($first->json('data.result'), $second->json('data.result'));
        $this->assertSame(1, TableSession::query()->where('table_id', $table->id)->where('status', TableSessionStatus::Open->value)->count());

        $command['id'] = (string) Str::uuid();
        $command['device_id'] = (string) Str::uuid();
        $this->api()->postJson(route('api.pos.commands.store'), $command)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('device_id');
    }

    public function test_sync_processes_commands_in_order_and_returns_a_fresh_table_snapshot(): void
    {
        $order = $this->openOrder();
        $product = Product::query()->where('store_id', $this->store->id)->where('is_active', true)->firstOrFail();
        $printer = $this->printer(PrinterType::Kitchen);

        $response = $this->api()->postJson(route('api.pos.sync'), [
            'device_id' => $this->deviceId,
            'commands' => [
                [
                    'id' => (string) Str::uuid(),
                    'type' => 'add_order_items',
                    'payload' => [
                        'table_id' => $order->tableSession->table_id,
                        'order_id' => $order->id,
                        'items' => [['product_id' => $product->id, 'quantity' => 2]],
                    ],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'type' => 'create_kitchen_ticket',
                    'payload' => ['table_id' => $order->tableSession->table_id, 'order_id' => $order->id, 'printer_id' => $printer->id],
                ],
            ],
        ])->assertOk()
            ->assertJsonCount(2, 'data.commands')
            ->assertJsonPath('data.commands.0.status', PosCommandStatus::Completed->value)
            ->assertJsonPath('data.commands.1.status', PosCommandStatus::Completed->value)
            ->assertJsonCount(1, 'data.changed_tables')
            ->assertJsonPath('data.changed_tables.0.id', $order->tableSession->table_id);

        $this->assertNotNull($response->json('data.server_time'));
        $this->assertSame(2, $order->items()->firstOrFail()->quantity);
        $this->assertSame(1, $order->printJobs()->where('print_type', PrintType::Kitchen->value)->count());
    }

    public function test_android_can_claim_and_report_a_print_job_with_user_and_claim_tokens(): void
    {
        $printer = $this->printer(PrinterType::Kitchen);
        PrintJob::query()->where('printer_id', $printer->id)->update(['status' => PrintJobStatus::Cancelled->value]);
        $order = Order::query()->where('store_id', $this->store->id)->firstOrFail();
        $job = PrintJob::create([
            'store_id' => $this->store->id,
            'printer_id' => $printer->id,
            'order_id' => $order->id,
            'print_type' => PrintType::Kitchen,
            'status' => PrintJobStatus::Pending,
            'attempts' => 0,
            'payload' => ['version' => 1, 'items' => [['name' => 'Món test', 'quantity' => 1]]],
        ]);

        $claimToken = $this->api()
            ->postJson(route('api.pos.print-jobs.claim', $printer->id))
            ->assertOk()
            ->assertJsonPath('data.id', $job->id)
            ->assertJsonPath('data.status', PrintJobStatus::Printing->value)
            ->json('data.claim_token');

        $this->api()->patchJson(route('api.pos.print-jobs.result', $job->id), [
            'status' => 'printing',
            'claim_token' => $claimToken,
        ])->assertOk();

        $this->api()->patchJson(route('api.pos.print-jobs.result', $job->id), [
            'status' => 'printed',
            'claim_token' => $claimToken,
        ])->assertOk()
            ->assertJsonPath('data.status', PrintJobStatus::Printed->value);

        $this->assertNotNull($job->refresh()->printed_at);
    }

    public function test_android_cannot_read_or_update_print_jobs_from_another_store(): void
    {
        $foreignPrinter = Printer::query()->where('store_id', '!=', $this->store->id)->firstOrFail();
        $foreignJob = PrintJob::query()->where('store_id', '!=', $this->store->id)->firstOrFail();

        $this->api()->postJson(route('api.pos.print-jobs.claim', $foreignPrinter->id))->assertNotFound();
        $this->api()->patchJson(route('api.pos.print-jobs.result', $foreignJob->id), [
            'status' => 'printed',
            'claim_token' => str_repeat('a', 64),
        ])->assertNotFound();
    }

    public function test_android_can_requeue_a_printed_receipt_without_creating_a_new_job(): void
    {
        $job = PrintJob::query()
            ->where('store_id', $this->store->id)
            ->where('print_type', PrintType::Receipt->value)
            ->firstOrFail();
        $job->forceFill([
            'status' => PrintJobStatus::Printed,
            'attempts' => 1,
            'printed_at' => now(),
        ])->save();
        $payload = $job->payload;

        $this->api()->postJson(route('api.pos.print-jobs.retry', $job->id))
            ->assertOk()
            ->assertJsonPath('data.id', $job->id)
            ->assertJsonPath('data.status', PrintJobStatus::Pending->value)
            ->assertJsonPath('data.attempts', 1);

        $this->assertSame($payload, $job->refresh()->payload);
        $this->assertNull($job->printed_at);
    }

    private function api(): static
    {
        return $this->withToken($this->token)->withHeader('X-Device-ID', $this->deviceId);
    }

    private function availableTable(): DiningTable
    {
        return DiningTable::query()
            ->where('store_id', $this->store->id)
            ->whereDoesntHave('sessions', fn ($query) => $query->where('status', TableSessionStatus::Open->value))
            ->firstOrFail();
    }

    private function openOrder(): Order
    {
        $response = $this->api()->postJson(route('api.pos.commands.store'), [
            'id' => (string) Str::uuid(),
            'device_id' => $this->deviceId,
            'type' => 'open_table',
            'payload' => ['table_id' => $this->availableTable()->id],
        ])->assertOk();

        return Order::query()->findOrFail($response->json('data.result.order_id'));
    }

    private function printer(PrinterType $type): Printer
    {
        return Printer::query()
            ->where('store_id', $this->store->id)
            ->where('printer_type', $type->value)
            ->where('is_active', true)
            ->firstOrFail();
    }
}
