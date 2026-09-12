<?php

namespace Tests\Feature;

use App\Actions\Pos\RetryPrintJob;
use App\Enums\PrinterType;
use App\Enums\PrintJobStatus;
use App\Enums\PrintType;
use App\Models\Order;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Kiểm tra credential máy in và toàn bộ transition của delivery API. */
class PrintJobDeliveryApiTest extends TestCase
{
    use RefreshDatabase;

    /** Dữ liệu seed cung cấp policy, tenant, order và printer thật cho test tích hợp. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /** Agent hợp lệ claim job rồi callback thành công bằng đúng claim token. */
    public function test_printer_agent_can_claim_and_complete_a_pending_job(): void
    {
        [$printer, $job] = $this->pendingJob();
        $token = $printer->issueApiToken();

        $claimResponse = $this->withToken($token)->postJson(route('api.print-jobs.claim', $printer));
        $claimResponse
            ->assertOk()
            ->assertJsonPath('data.id', $job->id)
            ->assertJsonPath('data.status', PrintJobStatus::Printing->value)
            ->assertJsonPath('data.printer.paper_width_mm', 80)
            ->assertJsonPath('data.printer.dots_per_line', 576);
        $claimToken = $claimResponse->json('data.claim_token');

        $this->assertSame(64, strlen($claimToken));
        $this->assertDatabaseHas('print_jobs', [
            'id' => $job->id,
            'status' => PrintJobStatus::Printing->value,
            'attempts' => 1,
        ]);

        $this->withToken($token)
            ->postJson(route('api.print-jobs.printed', [$printer, $job]), ['claim_token' => $claimToken])
            ->assertOk()
            ->assertJsonPath('data.status', PrintJobStatus::Printed->value);

        $this->assertNotNull($job->refresh()->printed_at);

        // Callback lặp do mất response phải trả cùng kết quả, không đổi attempts.
        $this->withToken($token)
            ->postJson(route('api.print-jobs.printed', [$printer, $job]), ['claim_token' => $claimToken])
            ->assertOk()
            ->assertJsonPath('data.status', PrintJobStatus::Printed->value);
        $this->assertSame(1, $job->refresh()->attempts);
    }

    /** Agent báo printing để gia hạn lease trước khi gửi một tài liệu raster dài. */
    public function test_printer_agent_can_renew_the_active_printing_lease(): void
    {
        [$printer, $job] = $this->pendingJob();
        $token = $printer->issueApiToken();
        $claimResponse = $this->withToken($token)
            ->postJson(route('api.print-jobs.claim', $printer))
            ->assertOk();
        $claimToken = $claimResponse->json('data.claim_token');
        $originalLease = $job->refresh()->lease_expires_at;
        $this->travel(30)->seconds();

        $this->withToken($token)
            ->postJson(route('api.print-jobs.printing', [$printer, $job]), ['claim_token' => $claimToken])
            ->assertOk()
            ->assertJsonPath('data.status', PrintJobStatus::Printing->value);

        $this->assertTrue($job->refresh()->lease_expires_at->greaterThan($originalLease));
    }

    /** Một máy chỉ nhận một job tại một thời điểm cho đến khi lease hiện tại kết thúc. */
    public function test_printer_cannot_claim_a_second_job_while_a_lease_is_active(): void
    {
        [$printer] = $this->pendingJob();
        $firstPendingJob = PrintJob::query()
            ->where('printer_id', $printer->id)
            ->where('status', PrintJobStatus::Pending->value)
            ->firstOrFail();
        $secondPendingJob = $firstPendingJob->replicate(['payment_id']);
        $secondPendingJob->save();
        $token = $printer->issueApiToken();

        $this->withToken($token)->postJson(route('api.print-jobs.claim', $printer))->assertOk();
        $this->withToken($token)->postJson(route('api.print-jobs.claim', $printer))->assertNoContent();

        $this->assertSame(PrintJobStatus::Pending, $secondPendingJob->refresh()->status);
    }

    /** Callback của lease đã cấp vẫn hợp lệ nếu owner vừa vô hiệu hóa máy in. */
    public function test_disabled_printer_can_finish_an_existing_claim(): void
    {
        [$printer, $job] = $this->pendingJob();
        $token = $printer->issueApiToken();
        $claimToken = $this->withToken($token)
            ->postJson(route('api.print-jobs.claim', $printer))
            ->assertOk()
            ->json('data.claim_token');

        $printer->update(['is_active' => false]);

        $this->withToken($token)
            ->postJson(route('api.print-jobs.printed', [$printer, $job]), ['claim_token' => $claimToken])
            ->assertOk()
            ->assertJsonPath('data.status', PrintJobStatus::Printed->value);
    }

    /** Token sai bị chặn và callback không thể tác động job thuộc máy khác. */
    public function test_delivery_api_rejects_invalid_credentials_and_cross_printer_job(): void
    {
        [$printer, $job] = $this->pendingJob();
        $otherPrinter = Printer::query()->whereKeyNot($printer->id)->firstOrFail();
        $otherToken = $otherPrinter->issueApiToken();

        $this->withToken('invalid-token')->postJson(route('api.print-jobs.claim', $printer))->assertUnauthorized();
        $this->withToken($otherToken)
            ->postJson(route('api.print-jobs.printed', [$otherPrinter, $job]), ['claim_token' => str_repeat('a', 64)])
            ->assertNotFound();
    }

    /** Job lỗi được retry bằng cùng payload và chỉ tăng attempts khi agent claim lại. */
    public function test_failed_job_can_be_retried_without_changing_payload(): void
    {
        [$printer, $job] = $this->pendingJob();
        $token = $printer->issueApiToken();
        $claimToken = $this->withToken($token)
            ->postJson(route('api.print-jobs.claim', $printer))
            ->assertOk()
            ->json('data.claim_token');

        $this->withToken($token)
            ->postJson(route('api.print-jobs.failed', [$printer, $job]), [
                'claim_token' => $claimToken,
                'error_message' => 'Không kết nối được cổng 9100.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', PrintJobStatus::Failed->value);

        $originalPayload = $job->refresh()->payload;
        $owner = User::query()->where('email', 'owner@example.com')->firstOrFail();
        app(RetryPrintJob::class)->handle($job, $owner);

        $this->assertSame($originalPayload, $job->refresh()->payload);
        $this->assertSame(1, $job->attempts);
        $this->assertSame(PrintJobStatus::Pending, $job->status);

        $this->withToken($token)
            ->postJson(route('api.print-jobs.claim', $printer))
            ->assertOk()
            ->assertJsonPath('data.id', $job->id)
            ->assertJsonPath('data.attempt', 2);
    }

    /** Tạo một job pending khớp Store của máy để tập trung test delivery lifecycle. */
    private function pendingJob(): array
    {
        $printer = Printer::query()
            ->where('printer_type', PrinterType::Kitchen->value)
            ->where('is_active', true)
            ->firstOrFail();
        $order = Order::query()->where('store_id', $printer->store_id)->firstOrFail();
        $job = PrintJob::create([
            'store_id' => $printer->store_id,
            'printer_id' => $printer->id,
            'order_id' => $order->id,
            'print_type' => PrintType::Kitchen,
            'status' => PrintJobStatus::Pending,
            'attempts' => 0,
            'payload' => ['version' => 1, 'items' => [['name' => 'Món test', 'quantity' => 1]]],
        ]);

        return [$printer, $job];
    }
}
