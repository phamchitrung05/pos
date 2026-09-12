<?php

namespace App\Http\Controllers\Api;

use App\Actions\Pos\ClaimNextPrintJob;
use App\Actions\Pos\MarkPrintJobFailed;
use App\Actions\Pos\MarkPrintJobPrinted;
use App\Actions\Pos\RenewPrintJobLease;
use App\Http\Controllers\Controller;
use App\Models\Printer;
use App\Models\PrintJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** API tối thiểu để Tauri agent nhận và phản hồi lệnh in của một máy cụ thể. */
final class PrintJobDeliveryController extends Controller
{
    /** Claim lệnh chờ lâu nhất và trả payload cùng lease cho thiết bị. */
    public function claim(Request $request, Printer $printer, ClaimNextPrintJob $claimNextPrintJob): JsonResponse|Response
    {
        $this->authenticatePrinter($request, $printer);
        $result = $claimNextPrintJob->handle($printer);

        if ($result === null) {
            return response()->noContent();
        }

        $job = $result['job'];

        return response()->json([
            'data' => [
                'id' => (int) $job->getKey(),
                'print_type' => $job->print_type->value,
                'status' => $job->status->value,
                'attempt' => $job->attempts,
                'claim_token' => $result['claimToken'],
                'lease_expires_at' => $job->lease_expires_at?->toIso8601String(),
                'printer' => [
                    'id' => (int) $printer->getKey(),
                    'name' => $printer->name,
                    'ip_address' => $printer->ip_address,
                    'port' => $printer->port,
                    'paper_width_mm' => $printer->paper_width_mm->value,
                    'dots_per_line' => $printer->paper_width_mm->dotsPerLine(),
                ],
                'payload' => $job->payload,
            ],
        ]);
    }

    /** Xác nhận agent đã bắt đầu gửi dữ liệu và gia hạn lease cho tài liệu dài. */
    public function printing(Request $request, Printer $printer, PrintJob $printJob, RenewPrintJobLease $renewLease): JsonResponse
    {
        $this->authenticatePrinter($request, $printer, requireActive: false);
        $this->ensureJobBelongsToPrinter($printJob, $printer);
        $validated = $request->validate(['claim_token' => ['required', 'string', 'size:64']]);
        $job = $renewLease->handle($printJob, $validated['claim_token']);

        return response()->json([
            'data' => [
                'id' => (int) $job->getKey(),
                'status' => $job->status->value,
                'lease_expires_at' => $job->lease_expires_at?->toIso8601String(),
            ],
        ]);
    }

    /** Xác nhận job đã được máy in xử lý thành công. */
    public function printed(Request $request, Printer $printer, PrintJob $printJob, MarkPrintJobPrinted $markPrinted): JsonResponse
    {
        // Máy có thể bị tắt sau khi đã gửi bytes; vẫn nhận callback của lease hợp lệ để tránh in lặp.
        $this->authenticatePrinter($request, $printer, requireActive: false);
        $this->ensureJobBelongsToPrinter($printJob, $printer);
        $validated = $request->validate(['claim_token' => ['required', 'string', 'size:64']]);
        $job = $markPrinted->handle($printJob, $validated['claim_token']);

        return response()->json(['data' => ['id' => (int) $job->getKey(), 'status' => $job->status->value, 'printed_at' => $job->printed_at?->toIso8601String()]]);
    }

    /** Ghi lỗi kết nối/in và giữ payload để owner hoặc staff retry. */
    public function failed(Request $request, Printer $printer, PrintJob $printJob, MarkPrintJobFailed $markFailed): JsonResponse
    {
        $this->authenticatePrinter($request, $printer, requireActive: false);
        $this->ensureJobBelongsToPrinter($printJob, $printer);
        $validated = $request->validate([
            'claim_token' => ['required', 'string', 'size:64'],
            'error_message' => ['required', 'string', 'max:1000'],
        ]);
        $job = $markFailed->handle($printJob, $validated['claim_token'], $validated['error_message']);

        return response()->json(['data' => ['id' => (int) $job->getKey(), 'status' => $job->status->value]]);
    }

    /** Xác thực Bearer token bằng hash constant-time mà không ghi plaintext vào database. */
    private function authenticatePrinter(Request $request, Printer $printer, bool $requireActive = true): void
    {
        $token = $request->bearerToken();
        $valid = filled($token)
            && filled($printer->api_token_hash)
            && hash_equals($printer->api_token_hash, hash('sha256', (string) $token));

        abort_unless($valid, 401, 'Token thiết bị in không hợp lệ.');
        abort_unless(! $requireActive || $printer->is_active, 403, 'Máy in đã bị vô hiệu hóa.');
    }

    /** Route ID không được phép đổi job sang máy khác dù token máy in là hợp lệ. */
    private function ensureJobBelongsToPrinter(PrintJob $printJob, Printer $printer): void
    {
        abort_unless((int) $printJob->printer_id === (int) $printer->getKey(), 404);
    }
}
