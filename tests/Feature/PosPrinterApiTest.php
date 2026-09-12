<?php

namespace Tests\Feature;

use App\Enums\PrinterType;
use App\Enums\PrintType;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Kiểm tra cấu hình máy in bill và template hóa đơn in thử của POS. */
class PosPrinterApiTest extends TestCase
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
            'device_name' => 'POS printer test',
        ])->assertOk()->json('data.access_token');
    }

    public function test_pos_can_create_a_receipt_printer_for_its_store(): void
    {
        $response = $this->api()->postJson(route('api.pos.printers.store'), [
            'name' => 'Máy in quầy mới',
            'ip_address' => '192.168.1.88',
            'port' => 9100,
            'paper_width_mm' => 80,
            'kitchen_copies' => 2,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Máy in quầy mới')
            ->assertJsonPath('data.type', PrinterType::Receipt->value)
            ->assertJsonPath('data.kitchen_copies', 2)
            ->assertJsonPath('data.dots_per_line', 576);

        $this->assertDatabaseHas('printers', [
            'store_id' => $this->store->id,
            'ip_address' => '192.168.1.88',
            'printer_type' => PrinterType::Receipt->value,
        ]);
    }

    public function test_pos_can_create_a_kitchen_printer_for_its_store(): void
    {
        $response = $this->api()->postJson(route('api.pos.printers.store'), [
            'name' => 'Máy in bếp mới',
            'printer_type' => PrinterType::Kitchen->value,
            'ip_address' => '192.168.1.89',
            'port' => 9100,
            'paper_width_mm' => 80,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.type', PrinterType::Kitchen->value);

        $this->assertDatabaseHas('printers', [
            'store_id' => $this->store->id,
            'ip_address' => '192.168.1.89',
            'printer_type' => PrinterType::Kitchen->value,
        ]);
    }

    public function test_pos_can_update_printer_settings_and_kitchen_copies(): void
    {
        $printer = Printer::query()
            ->where('store_id', $this->store->id)
            ->where('printer_type', PrinterType::Receipt->value)
            ->firstOrFail();

        $response = $this->api()->patchJson(route('api.pos.printers.update', $printer), [
            'name' => 'Máy in dùng chung',
            'printer_type' => PrinterType::Both->value,
            'ip_address' => '192.168.1.90',
            'port' => 9100,
            'paper_width_mm' => 80,
            'kitchen_copies' => 2,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.type', PrinterType::Both->value)
            ->assertJsonPath('data.kitchen_copies', 2);

        $this->assertDatabaseHas('printers', [
            'id' => $printer->id,
            'printer_type' => PrinterType::Both->value,
            'kitchen_copies' => 2,
        ]);
    }

    public function test_pos_can_queue_a_receipt_test_print_with_a_laravel_snapshot_template(): void
    {
        $printer = Printer::query()
            ->where('store_id', $this->store->id)
            ->where('printer_type', PrinterType::Receipt->value)
            ->where('is_active', true)
            ->firstOrFail();

        $response = $this->api()->postJson(route('api.pos.printers.test-print', $printer));

        $response
            ->assertAccepted()
            ->assertJsonPath('data.printer_id', $printer->id)
            ->assertJsonPath('data.print_type', PrintType::Receipt->value)
            ->assertJsonPath('data.status', 'pending');

        $job = PrintJob::query()->findOrFail($response->json('data.id'));
        $this->assertSame('TEST-PRINT', $job->payload['order']['code']);
        $this->assertSame('Phiếu in thử tiếng Việt', $job->payload['order']['items'][0]['notes']);
        $this->assertSame(576, $job->payload['document']['dots_per_line']);
        $this->assertNull($job->order_id);
        $this->assertNull($job->payment_id);
    }

    public function test_pos_cannot_create_a_test_print_for_another_store_printer(): void
    {
        $foreignPrinter = Printer::query()->where('store_id', '!=', $this->store->id)->firstOrFail();

        $this->api()->postJson(route('api.pos.printers.test-print', $foreignPrinter))->assertNotFound();
    }

    private function api(): static
    {
        return $this->withToken($this->token)->withHeader('X-Device-ID', $this->deviceId);
    }
}
