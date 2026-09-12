<?php

namespace App\Actions\Pos;

use App\Enums\PrintJobStatus;
use App\Enums\PrintType;
use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Đưa lệnh lỗi hoặc hóa đơn đã in về hàng đợi mà không tạo lại payload. */
final class RetryPrintJob
{
    public function handle(PrintJob $printJob, User $actor): PrintJob
    {
        return DB::transaction(function () use ($printJob, $actor): PrintJob {
            /** @var PrintJob $lockedJob */
            $lockedJob = PrintJob::query()->lockForUpdate()->findOrFail($printJob->getKey());
            Gate::forUser($actor)->authorize('update', $lockedJob);

            $isFailedJob = $lockedJob->status === PrintJobStatus::Failed;
            $isPrintedReceipt = $lockedJob->status === PrintJobStatus::Printed
                && $lockedJob->print_type === PrintType::Receipt;

            if (! $isFailedJob && ! $isPrintedReceipt) {
                throw ValidationException::withMessages([
                    'status' => 'Chỉ lệnh in thất bại hoặc hóa đơn đã in mới được đưa lại vào hàng đợi.',
                ]);
            }

            $lockedJob->forceFill([
                'status' => PrintJobStatus::Pending,
                'claim_token_hash' => null,
                'claimed_at' => null,
                'lease_expires_at' => null,
                'error_message' => null,
                'printed_at' => null,
            ])->save();

            return $lockedJob->refresh();
        });
    }
}
