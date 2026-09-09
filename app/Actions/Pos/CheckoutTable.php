<?php

namespace App\Actions\Pos;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TableSessionStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Ghi nhận thanh toán và đóng order cùng phiên bàn một cách nguyên tử. */
final class CheckoutTable
{
    public function __construct(private readonly RecalculateOrderTotal $recalculateOrderTotal) {}

    /**
     * `clientRequestId` là UUID do client giữ lại khi retry. Nếu request đầu đã
     * thành công nhưng client mất phản hồi, lần gọi lại trả đúng Payment cũ thay
     * vì thu tiền lần hai. Khi web không truyền ID, action tự sinh một UUID.
     */
    public function handle(
        Order $order,
        User $actor,
        PaymentMethod $paymentMethod = PaymentMethod::Cash,
        ?string $clientRequestId = null,
    ): Payment {
        $requestId = $clientRequestId ?? (string) Str::uuid();

        if (! Str::isUuid($requestId)) {
            throw ValidationException::withMessages([
                'client_request_id' => 'Mã yêu cầu thanh toán phải là UUID hợp lệ.',
            ]);
        }

        try {
            return DB::transaction(function () use ($order, $actor, $paymentMethod, $requestId): Payment {
                /** @var Order $lockedOrder */
                $lockedOrder = Order::query()
                    ->lockForUpdate()
                    ->findOrFail($order->getKey());

                Gate::forUser($actor)->authorize('update', $lockedOrder);
                Gate::forUser($actor)->authorize('create', Payment::class);

                // Kiểm tra idempotency sau khi khóa order để các lần retry cùng order chạy tuần tự.
                $existingPayment = Payment::query()
                    ->where('client_request_id', $requestId)
                    ->first();

                if ($existingPayment) {
                    if ((int) $existingPayment->order_id !== (int) $lockedOrder->getKey()) {
                        throw ValidationException::withMessages([
                            'client_request_id' => 'Mã yêu cầu thanh toán đã được dùng cho một order khác.',
                        ]);
                    }

                    return $existingPayment->load(['order.tableSession', 'receivedBy']);
                }

                /** @var TableSession $lockedSession */
                $lockedSession = TableSession::query()
                    ->lockForUpdate()
                    ->findOrFail($lockedOrder->table_session_id);

                Gate::forUser($actor)->authorize('update', $lockedSession);

                if ($lockedOrder->status !== OrderStatus::Open || $lockedSession->status !== TableSessionStatus::Open) {
                    throw ValidationException::withMessages([
                        'order_id' => 'Order hoặc phiên bàn đã được đóng, không thể thanh toán thêm lần nữa.',
                    ]);
                }

                $hasUnprintedKitchenItems = $lockedOrder->items()
                    ->whereColumn('quantity', '>', 'kitchen_printed_quantity')
                    ->exists();

                if ($hasUnprintedKitchenItems) {
                    throw ValidationException::withMessages([
                        'items' => 'Cần tạo phiếu bếp cho toàn bộ món mới trước khi thanh toán.',
                    ]);
                }

                // Tính lại ngay trong transaction để payment luôn dùng tổng mới nhất từ order items.
                $lockedOrder = $this->recalculateOrderTotal->handle($lockedOrder);

                if ((float) $lockedOrder->total <= 0) {
                    throw ValidationException::withMessages([
                        'amount' => 'Không thể thanh toán order chưa có món hoặc có tổng tiền bằng 0.',
                    ]);
                }

                $payment = new Payment;
                $payment->forceFill([
                    'store_id' => $lockedOrder->store_id,
                    'order_id' => $lockedOrder->getKey(),
                    'received_by' => $actor->getKey(),
                    'amount' => $lockedOrder->total,
                    'payment_method' => $paymentMethod,
                    'status' => PaymentStatus::Completed,
                    'paid_at' => now(),
                    'client_request_id' => $requestId,
                ]);
                $payment->save();

                // Chỉ đóng order và phiên sau khi payment đã được ghi thành công.
                $lockedOrder->forceFill([
                    'status' => OrderStatus::Paid,
                ])->save();

                $lockedSession->forceFill([
                    'status' => TableSessionStatus::Closed,
                    'end_time' => now(),
                    'closed_by' => $actor->getKey(),
                ])->save();

                return $payment->refresh()->load(['order.tableSession', 'receivedBy']);
            });
        } catch (QueryException $exception) {
            // Unique index là lớp bảo vệ cuối khi hai order khác nhau vô tình dùng cùng UUID.
            $existingPayment = Payment::query()
                ->where('client_request_id', $requestId)
                ->first();

            if (! $existingPayment) {
                throw $exception;
            }

            if ((int) $existingPayment->order_id !== (int) $order->getKey()) {
                throw ValidationException::withMessages([
                    'client_request_id' => 'Mã yêu cầu thanh toán đã được dùng cho một order khác.',
                ]);
            }

            return $existingPayment->load(['order.tableSession', 'receivedBy']);
        }
    }
}
