<?php

namespace App\Queries\Pos;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TableSessionStatus;
use App\Models\Order;
use App\Models\Printer;
use App\Models\Store;
use App\Models\TableZone;
use App\Models\User;

/** Dựng response đọc tenant-safe dành riêng cho ứng dụng Tauri. */
final class PosApiReadModel
{
    public function __construct(private readonly TableMapReadModel $tableMapReadModel) {}

    /** @return array<string, mixed> */
    public function bootstrap(Store $store, User $user): array
    {
        return [
            'server_time' => now()->toIso8601String(),
            'user' => [
                'id' => (int) $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
            ],
            'store' => [
                'id' => (int) $store->getKey(),
                'name' => $store->name,
                'address' => $store->address,
                'phone' => $store->phone,
            ],
            'zones' => TableZone::query()
                ->where('store_id', $store->getKey())
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (TableZone $zone): array => ['id' => (int) $zone->getKey(), 'name' => $zone->name])
                ->values()
                ->all(),
            'catalog' => $this->tableMapReadModel->catalog($store),
            'printers' => Printer::query()
                ->where('store_id', $store->getKey())
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Printer $printer): array => [
                    'id' => (int) $printer->getKey(),
                    'name' => $printer->name,
                    'type' => $printer->printer_type->value,
                    'ip_address' => $printer->ip_address,
                    'port' => $printer->port,
                    'paper_width_mm' => $printer->paper_width_mm->value,
                    'dots_per_line' => $printer->paper_width_mm->dotsPerLine(),
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function order(Order $order): array
    {
        $order->load(['tableSession.table', 'items.product', 'payments.receivedBy', 'printJobs.printer']);

        return [
            'id' => (int) $order->getKey(),
            'code' => $order->code,
            'status' => $order->status->value,
            'notes' => $order->notes,
            'total' => (int) round((float) $order->total),
            'table_session' => [
                'id' => (int) $order->table_session_id,
                'status' => $order->tableSession->status->value,
                'table' => [
                    'id' => (int) $order->tableSession->table_id,
                    'name' => $order->tableSession->table?->name,
                ],
            ],
            'items' => $order->items->map(fn ($item): array => [
                'id' => (int) $item->getKey(),
                'product_id' => (int) $item->product_id,
                'name' => $item->product?->name ?? 'Sản phẩm đã xóa',
                'quantity' => $item->quantity,
                'kitchen_printed_quantity' => $item->kitchen_printed_quantity,
                'unit_price' => (int) round((float) $item->unit_price),
                'subtotal' => (int) round((float) $item->unit_price * $item->quantity),
                'notes' => $item->notes,
            ])->values()->all(),
            'payments' => $order->payments->map(fn ($payment): array => [
                'id' => (int) $payment->getKey(),
                'amount' => (int) round((float) $payment->amount),
                'method' => $payment->payment_method->value,
                'status' => $payment->status->value,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'received_by' => $payment->receivedBy?->name,
            ])->values()->all(),
            'print_jobs' => $order->printJobs->map(fn ($job): array => [
                'id' => (int) $job->getKey(),
                'printer_id' => (int) $job->printer_id,
                'printer_name' => $job->printer?->name,
                'type' => $job->print_type->value,
                'status' => $job->status->value,
                'attempts' => $job->attempts,
                'error' => $job->error_message,
                'printed_at' => $job->printed_at?->toIso8601String(),
            ])->values()->all(),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }

    /** @return array{date: string, orders: array<int, array<string, mixed>>} */
    public function todayOrders(Store $store): array
    {
        $today = today();
        $orders = Order::query()
            ->where('store_id', $store->getKey())
            ->where('status', OrderStatus::Paid)
            ->whereHas('tableSession', fn ($query) => $query->where('status', TableSessionStatus::Closed))
            ->whereHas('payments', fn ($query) => $query
                ->where('status', PaymentStatus::Completed)
                ->where('paid_at', '>=', $today->copy()->startOfDay())
                ->where('paid_at', '<', $today->copy()->addDay()->startOfDay()))
            ->with([
                'tableSession.table',
                'items.product',
                'payments' => fn ($query) => $query
                    ->where('status', PaymentStatus::Completed)
                    ->whereNotNull('paid_at')
                    ->latest('paid_at'),
            ])
            ->orderByDesc(
                \App\Models\Payment::query()
                    ->select('paid_at')
                    ->whereColumn('payments.order_id', 'orders.id')
                    ->where('status', PaymentStatus::Completed)
                    ->latest('paid_at')
                    ->limit(1),
            )
            ->latest('id')
            ->get();

        return [
            'date' => $today->toDateString(),
            'orders' => $orders->map(function (Order $order): array {
                $completedPayment = $order->payments->first();

                return [
                    'id' => (int) $order->getKey(),
                    'code' => $order->code,
                    'tableName' => $order->tableSession->table?->name ?? 'Bàn đã xóa',
                    'timeLabel' => $completedPayment?->paid_at?->format('H:i') ?? '--:--',
                    'status' => $order->status->value,
                    'statusLabel' => $order->status->getLabel(),
                    'total' => (int) round((float) $order->total),
                    'items' => $order->items->map(fn ($item): array => [
                        'id' => (int) $item->getKey(),
                        'name' => $item->product?->name ?? 'Sản phẩm đã xóa',
                        'quantity' => (int) $item->quantity,
                        'unitPrice' => (int) round((float) $item->unit_price),
                        'subtotal' => (int) round((float) $item->unit_price * $item->quantity),
                        'notes' => $item->notes,
                    ])->values()->all(),
                ];
            })->values()->all(),
        ];
    }
}
