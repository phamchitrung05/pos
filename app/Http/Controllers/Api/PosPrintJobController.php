<?php

namespace App\Http\Controllers\Api;

use App\Actions\Pos\ClaimNextPrintJob;
use App\Actions\Pos\MarkPrintJobFailed;
use App\Actions\Pos\MarkPrintJobPrinted;
use App\Actions\Pos\RenewPrintJobLease;
use App\Actions\Pos\RetryPrintJob;
use App\Http\Controllers\Controller;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Giao PrintJob cho Android và nhận kết quả bằng user token cùng claim token. */
final class PosPrintJobController extends Controller
{
    public function claim(Request $request, int $printer, ClaimNextPrintJob $claimNextPrintJob): JsonResponse|Response
    {
        [$user, $store] = $this->context($request);
        $printerModel = Printer::query()->where('store_id', $store->getKey())->findOrFail($printer);
        Gate::forUser($user)->authorize('view', $printerModel);
        $result = $claimNextPrintJob->handle($printerModel);

        if ($result === null) {
            return response()->noContent();
        }

        $job = $result['job'];

        return response()->json([
            'data' => [
                ...$this->jobResult($job),
                'attempt' => $job->attempts,
                'claim_token' => $result['claimToken'],
                'lease_expires_at' => $job->lease_expires_at?->toIso8601String(),
                'printer' => [
                    'id' => (int) $printerModel->getKey(),
                    'name' => $printerModel->name,
                    'ip_address' => $printerModel->ip_address,
                    'port' => $printerModel->port,
                    'paper_width_mm' => $printerModel->paper_width_mm->value,
                    'dots_per_line' => $printerModel->paper_width_mm->dotsPerLine(),
                ],
                'payload' => $job->payload,
            ],
        ]);
    }

    public function updateResult(
        Request $request,
        int $printJob,
        RenewPrintJobLease $renewLease,
        MarkPrintJobPrinted $markPrinted,
        MarkPrintJobFailed $markFailed,
    ): JsonResponse {
        [$user, $store] = $this->context($request);
        $job = PrintJob::query()->where('store_id', $store->getKey())->findOrFail($printJob);
        Gate::forUser($user)->authorize('update', $job);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['printing', 'printed', 'failed'])],
            'claim_token' => ['required', 'string', 'size:64'],
            'error_message' => ['required_if:status,failed', 'nullable', 'string', 'max:1000'],
        ]);

        $job = match ($validated['status']) {
            'printing' => $renewLease->handle($job, $validated['claim_token']),
            'printed' => $markPrinted->handle($job, $validated['claim_token']),
            'failed' => $markFailed->handle($job, $validated['claim_token'], $validated['error_message']),
        };

        return response()->json(['data' => $this->jobResult($job)]);
    }

    public function retry(Request $request, int $printJob, RetryPrintJob $retryPrintJob): JsonResponse
    {
        [$user, $store] = $this->context($request);
        $job = PrintJob::query()->where('store_id', $store->getKey())->findOrFail($printJob);
        $job = $retryPrintJob->handle($job, $user);

        return response()->json(['data' => $this->jobResult($job)]);
    }

    /** @return array<string, mixed> */
    private function jobResult(PrintJob $job): array
    {
        return [
            'id' => (int) $job->getKey(),
            'print_type' => $job->print_type->value,
            'status' => $job->status->value,
            'attempts' => $job->attempts,
            'error_message' => $job->error_message,
            'printed_at' => $job->printed_at?->toIso8601String(),
        ];
    }

    /** @return array{User, Store} */
    private function context(Request $request): array
    {
        $user = $request->user();
        $store = $request->attributes->get('pos_store');
        abort_unless($user instanceof User && $store instanceof Store, 403);

        return [$user, $store];
    }
}
