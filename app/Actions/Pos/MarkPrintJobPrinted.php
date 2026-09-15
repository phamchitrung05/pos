<?php

namespace App\Actions\Pos;

use App\Enums\PrintJobStatus;
use App\Models\PrintJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Xác nhận in thành công bằng claim token của đúng lượt giao job. */
final class MarkPrintJobPrinted
{
    public function handle(PrintJob $printJob, string $claimToken): PrintJob
    {
        return DB::transaction(function () use ($printJob, $claimToken): PrintJob {
            /** @var PrintJob $lockedJob */
            $lockedJob = PrintJob::query()->lockForUpdate()->findOrFail($printJob->getKey());
            $this->ensureClaimMatches($lockedJob, $claimToken);

            // Callback lặp lại sau lỗi mạng trả cùng kết quả thay vì báo chuyển trạng thái sai.
            if ($lockedJob->status === PrintJobStatus::Printed) {
                return $lockedJob;
            }

            if ($lockedJob->status !== PrintJobStatus::Printing) {
                throw ValidationException::withMessages(['status' => 'Chỉ lệnh đang in mới có thể hoàn tất.']);
            }

            $lockedJob->forceFill([
                'status' => PrintJobStatus::Printed,
                'printed_at' => now(),
                'lease_expires_at' => null,
                'error_message' => null,
            ])->save();

            return $lockedJob->refresh();
        });
    }

    /** So sánh constant-time để không làm lộ thông tin claim token qua timing. */
    private function ensureClaimMatches(PrintJob $job, string $claimToken): void
    {
        if (blank($job->claim_token_hash) || ! hash_equals($job->claim_token_hash, hash('sha256', $claimToken))) {
            throw ValidationException::withMessages(['claim_token' => 'Mã nhận lệnh không hợp lệ cho lệnh in này.']);
        }
    }
}
