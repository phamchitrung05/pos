<?php

namespace App\Actions\Pos;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Hoàn tất thanh toán trước, sau đó xếp hàng hóa đơn mà không rollback Payment khi in lỗi. */
final class CheckoutAndQueueReceipt
{
    public function __construct(
        private readonly CheckoutTable $checkoutTable,
        private readonly CreateReceiptPrintJob $createReceiptPrintJob,
    ) {}

    /**
     * @return array{payment: Payment, receiptPrintJob: PrintJob|null, receiptError: string|null}
     */
    public function handle(
        Order $order,
        User $actor,
        ?Printer $printer,
        PaymentMethod $paymentMethod = PaymentMethod::Cash,
        ?string $clientRequestId = null,
        bool $allowUnprintedKitchenItems = false,
    ): array {
        if ($printer) {
            // Chặn lựa chọn máy chéo tenant trước khi bất kỳ khoản thanh toán nào được ghi.
            Gate::forUser($actor)->authorize('view', $printer);
        }

        $payment = $this->checkoutTable->handle(
            $order,
            $actor,
            $paymentMethod,
            $clientRequestId,
            $allowUnprintedKitchenItems,
        );

        if (! $printer) {
            return [
                'payment' => $payment,
                'receiptPrintJob' => null,
                'receiptError' => 'Chi nhánh chưa có máy in hóa đơn phù hợp.',
            ];
        }

        try {
            $printJob = $this->createReceiptPrintJob->handle($payment, $printer, $actor);
        } catch (Throwable $exception) {
            report($exception);

            return [
                'payment' => $payment,
                'receiptPrintJob' => null,
                'receiptError' => $this->receiptErrorMessage($exception),
            ];
        }

        return [
            'payment' => $payment,
            'receiptPrintJob' => $printJob,
            'receiptError' => null,
        ];
    }

    /** Không trả chi tiết database nhạy cảm cho ứng dụng POS. */
    private function receiptErrorMessage(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            $message = collect($exception->errors())->flatten()->first();

            if (is_string($message) && $message !== '') {
                return $message;
            }
        }

        return 'Thanh toán đã hoàn tất nhưng chưa thể tạo lệnh in hóa đơn.';
    }
}
