<?php

namespace App\Actions\Pos;

use App\Enums\PrintJobStatus;
use App\Models\PrintJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Xác nhận agent đang in và gia hạn lease của đúng lượt claim hiện tại. */
final class RenewPrintJobLease
{
    public function handle(PrintJob $printJob, string $claimToken): PrintJob
    {
        return DB::transaction(function () use ($printJob, $claimToken): PrintJob {
            /** @var PrintJob $lockedJob */
            $lockedJob = PrintJob::query()->lockForUpdate()->findOrFail($printJob->getKey());

            if (blank($lockedJob->claim_token_hash)
                || ! hash_equals($lockedJob->claim_token_hash, hash('sha256', $claimToken))) {
                throw ValidationException::withMessages(['claim_token' => 'Claim token không hợp lệ cho lệnh in này.']);
            }

            if ($lockedJob->status !== PrintJobStatus::Printing) {
                throw ValidationException::withMessages(['status' => 'Chỉ lệnh đang in mới có thể gia hạn lease.']);
            }

            $lockedJob->forceFill([
                'lease_expires_at' => now()->addMinutes(2),
            ])->save();

            return $lockedJob->refresh();
        });
    }
}
