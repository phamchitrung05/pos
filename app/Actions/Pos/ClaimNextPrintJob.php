<?php

namespace App\Actions\Pos;

use App\Enums\PrintJobStatus;
use App\Models\Printer;
use App\Models\PrintJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Claim nguyên tử lệnh chờ lâu nhất để hai agent không cùng in một payload. */
final class ClaimNextPrintJob
{
    /**
     * @return array{job: PrintJob, claimToken: string}|null
     */
    public function handle(Printer $printer): ?array
    {
        return DB::transaction(function () use ($printer): ?array {
            /** @var Printer $lockedPrinter */
            $lockedPrinter = Printer::query()->lockForUpdate()->findOrFail($printer->getKey());

            if (! $lockedPrinter->is_active || blank($lockedPrinter->ip_address) || ! $lockedPrinter->port) {
                throw ValidationException::withMessages([
                    'printer' => 'Máy in phải hoạt động và có đủ địa chỉ IP, cổng kết nối.',
                ]);
            }

            // Agent bị tắt giữa chừng sẽ nhả job khi lease hết hạn để thiết bị khác nhận lại.
            PrintJob::query()
                ->where('printer_id', $lockedPrinter->getKey())
                ->where('status', PrintJobStatus::Printing->value)
                ->where('lease_expires_at', '<=', now())
                ->update([
                    'status' => PrintJobStatus::Pending->value,
                    'claim_token_hash' => null,
                    'claimed_at' => null,
                    'lease_expires_at' => null,
                    'error_message' => 'Thời gian xử lý trước đã hết hạn; hệ thống tự đưa lệnh về hàng đợi.',
                    'updated_at' => now(),
                ]);

            // Một máy vật lý chỉ xử lý một lease để hai agent không ghi TCP chồng lệnh.
            $hasActiveLease = PrintJob::query()
                ->where('printer_id', $lockedPrinter->getKey())
                ->where('status', PrintJobStatus::Printing->value)
                ->where('lease_expires_at', '>', now())
                ->exists();

            if ($hasActiveLease) {
                return null;
            }

            /** @var PrintJob|null $job */
            $job = PrintJob::query()
                ->where('printer_id', $lockedPrinter->getKey())
                ->where('status', PrintJobStatus::Pending->value)
                ->oldest('id')
                ->lockForUpdate()
                ->first();

            if (! $job) {
                return null;
            }

            $claimToken = Str::random(64);
            $job->forceFill([
                'status' => PrintJobStatus::Printing,
                'attempts' => $job->attempts + 1,
                'claim_token_hash' => hash('sha256', $claimToken),
                'claimed_at' => now(),
                'lease_expires_at' => now()->addMinutes(2),
                'error_message' => null,
            ])->save();

            return ['job' => $job->refresh(), 'claimToken' => $claimToken];
        });
    }
}
