<?php

namespace Tests\Feature;

use App\Actions\Pos\AddOrderItems;
use App\Actions\Pos\CheckoutTable;
use App\Actions\Pos\CreateKitchenPrintJob;
use App\Actions\Pos\OpenTableSession;
use App\Actions\Pos\UpdateOrderItem;
use App\Models\DiningTable;
use App\Models\Printer;
use App\Models\Product;
use App\Models\User;
use App\Queries\Pos\TableSessionActivityReadModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/** Đảm bảo nhật ký POS giữ được snapshot món trước và sau các thao tác. */
class PosActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_order_item_activity_keeps_added_updated_and_deleted_snapshots(): void
    {
        $actor = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $table = DiningTable::query()
            ->whereDoesntHave('sessions', fn ($query) => $query->where('status', 'open'))
            ->firstOrFail();
        $product = Product::query()->where('store_id', $table->store_id)->firstOrFail();
        $session = app(OpenTableSession::class)->handle($table, $actor);

        $order = app(AddOrderItems::class)->handle($session->order, $actor, [[
            'product_id' => $product->id,
            'quantity' => 2,
        ]]);
        $item = $order->items->firstOrFail();

        $this->assertActivity('order.item.added', $session->id, [
            'product_name' => $product->name,
            'added_quantity' => 2,
            'resulting_quantity' => 2,
        ]);

        app(CreateKitchenPrintJob::class)->handle($order, $this->kitchenPrinter($order->store_id), $actor);

        $this->assertActivity('kitchen.ticket.created', $session->id, [
            'items' => [[
                'product_name' => $product->name,
                'quantity' => 2,
            ]],
        ]);

        app(UpdateOrderItem::class)->handle($item, $actor, 4);

        $this->assertActivity('order.item.updated', $session->id, [
            'product_name' => $product->name,
            'old' => ['quantity' => 2],
            'new' => ['quantity' => 4],
        ]);

        app(UpdateOrderItem::class)->handle($item->refresh(), $actor, 0);

        $this->assertActivity('order.item.deleted', $session->id, [
            'product_name' => $product->name,
            'quantity' => 4,
        ]);

        $events = app(TableSessionActivityReadModel::class)->for($session);

        $this->assertSame(
            ['Hủy phiên bàn', 'Xóa món', 'Cập nhật món', 'Gửi chế biến', 'Thêm món', 'Mở phiên bàn'],
            $events->pluck('title')->all(),
        );
        $this->assertSame(sprintf('%s: số lượng 2 → 4', $product->name), $events[2]['description']);
    }

    public function test_checkout_activity_records_payment_and_closed_session(): void
    {
        $actor = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $table = DiningTable::query()
            ->whereDoesntHave('sessions', fn ($query) => $query->where('status', 'open'))
            ->firstOrFail();
        $product = Product::query()->where('store_id', $table->store_id)->firstOrFail();
        $session = app(OpenTableSession::class)->handle($table, $actor);
        $order = app(AddOrderItems::class)->handle($session->order, $actor, [[
            'product_id' => $product->id,
            'quantity' => 1,
        ]]);
        app(CreateKitchenPrintJob::class)->handle($order, $this->kitchenPrinter($order->store_id), $actor);

        $payment = app(CheckoutTable::class)->handle($order, $actor);

        $this->assertActivity('payment.completed', $session->id, [
            'amount' => (int) round((float) $payment->amount),
        ]);
        $this->assertActivity('session.closed', $session->id, [
            'action' => 'closed',
        ]);
    }

    private function kitchenPrinter(int $storeId): Printer
    {
        return Printer::query()
            ->where('store_id', $storeId)
            ->whereIn('printer_type', ['kitchen', 'both'])
            ->where('is_active', true)
            ->firstOrFail();
    }

    /** @param array<string, mixed> $properties */
    private function assertActivity(string $event, int $sessionId, array $properties): void
    {
        $activity = Activity::query()
            ->where('log_name', 'pos')
            ->where('event', $event)
            ->whereJsonContains('properties->table_session_id', $sessionId)
            ->latest('id')
            ->firstOrFail();

        foreach ($properties as $key => $expected) {
            if (is_array($expected)) {
                foreach (Arr::dot($expected) as $nestedKey => $nestedValue) {
                    $this->assertSame($nestedValue, data_get($activity->getExtraProperty($key), $nestedKey));
                }

                continue;
            }

            $this->assertSame($expected, $activity->getExtraProperty($key));
        }
    }
}
