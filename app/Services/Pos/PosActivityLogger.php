<?php

namespace App\Services\Pos;

use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PrintJob;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Ghi audit log theo ngữ nghĩa nghiệp vụ POS.
 *
 * Activitylog tự biết các thuộc tính trực tiếp của model, nhưng không tự suy
 * ra tên sản phẩm hoặc table_session_id qua nhiều quan hệ. Service này lưu
 * snapshot cần thiết để log vẫn đọc được sau khi OrderItem bị xóa hoặc Product
 * bị đổi tên.
 */
final class PosActivityLogger
{
    /** Ghi một activity thuộc log POS và gắn người thực hiện đã xác thực. */
    private function record(
        string $event,
        string $description,
        User $actor,
        ?Model $subject,
        array $properties,
    ): void {
        $logger = activity()
            ->useLog('pos')
            ->event($event)
            ->causedBy($actor)
            ->withProperties($properties);

        if ($subject !== null) {
            $logger->performedOn($subject);
        }

        $logger->log($description);
    }

    /** Ghi thao tác mở phiên và order đầu tiên của bàn. */
    public function sessionOpened(TableSession $session, User $actor): void
    {
        $session->loadMissing('table');

        $this->record(
            'session.opened',
            'Mở phiên bàn',
            $actor,
            $session,
            [
                'action' => 'opened',
                'table_session_id' => (int) $session->getKey(),
                'table_id' => (int) $session->table_id,
                'table_name' => $session->table?->name,
            ],
        );
    }

    /** Ghi món mới hoặc phần số lượng vừa được cộng vào dòng món hiện có. */
    public function itemAdded(OrderItem $item, User $actor, int $addedQuantity): void
    {
        $item->loadMissing(['product', 'order']);
        $order = $item->order;

        $this->record(
            'order.item.added',
            'Thêm món',
            $actor,
            $item,
            [
                'action' => 'added',
                'table_session_id' => (int) $order->table_session_id,
                'order_id' => (int) $item->order_id,
                'order_item_id' => (int) $item->getKey(),
                'product_id' => (int) $item->product_id,
                'product_name' => $item->product?->name,
                'added_quantity' => $addedQuantity,
                'resulting_quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'notes' => $item->notes,
            ],
        );
    }

    /** Ghi giá trị cũ và mới khi sửa số lượng, ghi chú hoặc giá món. */
    public function itemUpdated(OrderItem $item, User $actor, array $old, array $new): void
    {
        $item->loadMissing(['product', 'order']);
        $order = $item->order;

        $this->record(
            'order.item.updated',
            'Cập nhật món',
            $actor,
            $item,
            [
                'action' => 'updated',
                'table_session_id' => (int) $order->table_session_id,
                'order_id' => (int) $item->order_id,
                'order_item_id' => (int) $item->getKey(),
                'product_id' => (int) $item->product_id,
                'product_name' => $item->product?->name,
                'old' => $old,
                'new' => $new,
            ],
        );
    }

    /** Ghi snapshot đầy đủ trước khi dòng món bị xóa khỏi order. */
    public function itemDeleted(OrderItem $item, User $actor): void
    {
        $item->loadMissing(['product', 'order']);
        $order = $item->order;

        $this->record(
            'order.item.deleted',
            'Xóa món',
            $actor,
            $item,
            [
                'action' => 'deleted',
                'table_session_id' => (int) $order->table_session_id,
                'order_id' => (int) $item->order_id,
                'order_item_id' => (int) $item->getKey(),
                'product_id' => (int) $item->product_id,
                'product_name' => $item->product?->name,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'notes' => $item->notes,
            ],
        );
    }

    /** Ghi snapshot các món vừa được gửi xuống bếp trong một phiếu in. */
    public function kitchenTicketCreated(PrintJob $printJob, User $actor): void
    {
        $printJob->loadMissing('order');

        $this->record(
            'kitchen.ticket.created',
            'Gửi chế biến',
            $actor,
            $printJob,
            [
                'action' => 'sent_to_kitchen',
                'table_session_id' => (int) $printJob->order->table_session_id,
                'order_id' => (int) $printJob->order_id,
                'print_job_id' => (int) $printJob->getKey(),
                // PrintJob đã lưu snapshot tên và số lượng, nên activity không
                // phụ thuộc vào OrderItem sau khi món bị sửa hoặc xóa.
                'items' => $printJob->payload['items'] ?? [],
            ],
        );
    }

    /** Ghi thanh toán đã hoàn tất cho phiên bàn. */
    public function paymentCompleted(Payment $payment, User $actor): void
    {
        $payment->loadMissing('order');

        $this->record(
            'payment.completed',
            'Thanh toán hoàn tất',
            $actor,
            $payment,
            [
                'action' => 'paid',
                'table_session_id' => (int) $payment->order->table_session_id,
                'order_id' => (int) $payment->order_id,
                'payment_id' => (int) $payment->getKey(),
                'amount' => (int) round((float) $payment->amount),
                'payment_method' => $payment->payment_method?->value,
            ],
        );
    }

    /** Ghi việc đóng hoặc hủy phiên sau khi nghiệp vụ hoàn tất. */
    public function sessionClosed(TableSession $session, User $actor): void
    {
        $this->record(
            $session->status->value === 'closed' ? 'session.closed' : 'session.cancelled',
            $session->status->value === 'closed' ? 'Đóng phiên bàn' : 'Hủy phiên bàn',
            $actor,
            $session,
            [
                'action' => $session->status->value,
                'table_session_id' => (int) $session->getKey(),
                'table_id' => (int) $session->table_id,
                'ended_at' => $session->end_time?->toIso8601String(),
            ],
        );
    }
}
