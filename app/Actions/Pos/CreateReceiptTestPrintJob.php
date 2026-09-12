<?php

namespace App\Actions\Pos;

use App\Enums\PrinterType;
use App\Enums\PrintJobStatus;
use App\Enums\PrintType;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\Store;
use App\Models\User;
use App\PrintTemplates\ReceiptTestPrintTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Tạo lệnh in thử không gắn với order/payment thật. */
final class CreateReceiptTestPrintJob
{
    public function __construct(private readonly ReceiptTestPrintTemplate $template) {}

    public function handle(Store $store, Printer $printer, User $actor): PrintJob
    {
        return DB::transaction(function () use ($store, $printer, $actor): PrintJob {
            /** @var Printer $lockedPrinter */
            $lockedPrinter = Printer::query()->lockForUpdate()->findOrFail($printer->getKey());

            Gate::forUser($actor)->authorize('view', $lockedPrinter);
            Gate::forUser($actor)->authorize('create', PrintJob::class);

            if ((int) $lockedPrinter->store_id !== (int) $store->getKey()
                || ! $lockedPrinter->is_active
                || blank($lockedPrinter->ip_address)
                || ! $lockedPrinter->port
                || ! in_array($lockedPrinter->printer_type, [PrinterType::Receipt, PrinterType::Both], true)) {
                throw ValidationException::withMessages([
                    'printer_id' => 'Máy in hóa đơn không hợp lệ hoặc đang tắt.',
                ]);
            }

            $job = new PrintJob;
            $job->forceFill([
                'store_id' => $store->getKey(),
                'printer_id' => $lockedPrinter->getKey(),
                'order_id' => null,
                'payment_id' => null,
                'print_type' => PrintType::Receipt,
                'status' => PrintJobStatus::Pending,
                'attempts' => 0,
                'payload' => $this->template->render($store, $lockedPrinter, $actor),
            ]);
            $job->save();

            return $job->refresh()->load('printer');
        });
    }
}
