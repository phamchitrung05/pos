<?php

namespace App\Actions\Pos;

use App\Enums\PrintJobStatus;
use App\Models\PrintJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Lưu lỗi thiết bị nhưng giữ nguyên payload để có thể retry chính xác. */
final class MarkPrintJobFailed
{
    public function handle(PrintJob $printJob, string $claimToken, string $errorMessage): PrintJob
    {
        return DB::transaction(function () use ($printJob, $claimToken, $errorMessage): PrintJob {
            /** @var PrintJob $lockedJob */
            $lockedJob = PrintJob::query()->lockForUpdate()->findOrFail($printJob->getKey());

            if (blank($lockedJob->claim_token_hash) || ! hash_equals($lockedJob->claim_token_hash, hash('sha256', $claimToken))) {
                throw ValidationException::withMessages(['claim_token' => 'Mã nhận lệnh không hợp lệ cho lệnh in này.']);
            }

            if ($lockedJob->status === PrintJobStatus::Failed) {
                return $lockedJob;
            }

            if ($lockedJob->status !== PrintJobStatus::Printing) {
                throw ValidationException::withMessages(['status' => 'Chỉ lệnh đang in mới có thể báo lỗi.']);
            }

            $lockedJob->forceFill([
                'status' => PrintJobStatus::Failed,
                'lease_expires_at' => null,
                'error_message' => mb_substr(trim($errorMessage), 0, 1000),
            ])->save();

            return $lockedJob->refresh();
        });
    }
}
