<?php

namespace App\Actions\Pos;

use Carbon\CarbonImmutable;
use App\Enums\PaymentMethod;
use App\Enums\PosCommandStatus;
use App\Enums\PosCommandType;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosCommand;
use App\Models\Printer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

/** Thực thi command đúng một lần và trả lại kết quả đã lưu cho mọi request trùng UUID. */
final class ProcessPosCommand
{
    public function __construct(
        private readonly OpenTableSession $openTableSession,
        private readonly AddOrderItems $addOrderItems,
        private readonly UpdateOrderItem $updateOrderItem,
        private readonly CreateKitchenPrintJob $createKitchenPrintJob,
        private readonly CheckoutAndQueueReceipt $checkoutAndQueueReceipt,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(
        string $commandId,
        Store $store,
        string $deviceId,
        User $actor,
        PosCommandType $type,
        array $payload,
    ): PosCommand {
        $this->validateEnvelope($commandId, $deviceId, $store, $actor);
        $payloadHash = $this->payloadHash($payload);
        $now = now();

        // insertOrIgnore tạo điểm đồng bộ trước khi khóa, kể cả khi hai request đến đồng thời.
        PosCommand::query()->insertOrIgnore([
            'id' => $commandId,
            'store_id' => $store->getKey(),
            'device_id' => $deviceId,
            'user_id' => $actor->getKey(),
            'type' => $type->value,
            'payload_hash' => $payloadHash,
            'status' => PosCommandStatus::Pending->value,
            'attempts' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::transaction(function () use ($commandId, $store, $deviceId, $actor, $type, $payload, $payloadHash): PosCommand {
            /** @var PosCommand $command */
            $command = PosCommand::query()->lockForUpdate()->findOrFail($commandId);
            $this->ensureEnvelopeMatches($command, $store, $deviceId, $actor, $type, $payloadHash);

            if (in_array($command->status, [PosCommandStatus::Completed, PosCommandStatus::Failed], true)) {
                return $command;
            }

            $command->forceFill([
                'status' => PosCommandStatus::Processing,
                'attempts' => $command->attempts + 1,
                'error' => null,
            ])->save();

            try {
                $result = $this->execute($type, $store, $actor, $payload, $commandId);

                $command->forceFill([
                    'status' => PosCommandStatus::Completed,
                    'result' => $result,
                    'error' => null,
                    'processed_at' => now(),
                ])->save();
            } catch (Throwable $exception) {
                if (! $this->isExpectedException($exception)) {
                    report($exception);
                }

                $command->forceFill([
                    'status' => PosCommandStatus::Failed,
                    'result' => null,
                    'error' => $this->errorMessage($exception),
                    'processed_at' => now(),
                ])->save();
            }

            return $command->refresh();
        }, attempts: 3);
    }

    /** @param array<string, mixed> $payload */
    private function execute(PosCommandType $type, Store $store, User $actor, array $payload, string $commandId): array
    {
        return match ($type) {
            PosCommandType::OpenTable => $this->openTable($store, $actor, $payload),
            PosCommandType::AddOrderItems => $this->addItems($store, $actor, $payload),
            PosCommandType::UpdateOrderItem => $this->updateItem($store, $actor, $payload),
            PosCommandType::CreateKitchenTicket => $this->createKitchenTicket($store, $actor, $payload),
            PosCommandType::Checkout => $this->checkout($store, $actor, $payload, $commandId),
        };
    }

    /** @param array<string, mixed> $payload */
    private function openTable(Store $store, User $actor, array $payload): array
    {
        $validated = Validator::make($payload, [
            'table_id' => ['required', 'integer'],
            // Mốc quá khứ là hợp lệ cho phiên nháp/offline; clock lệch sang tương lai sẽ được chặn về now bên dưới.
            'started_at' => ['nullable', 'date'],
        ])->validate();
        $table = DiningTable::query()->where('store_id', $store->getKey())->findOrFail($validated['table_id']);
        // Cột timestamp không giữ timezone, nên đổi ISO UTC của thiết bị sang timezone ứng dụng trước khi persist.
        $startedAt = isset($validated['started_at'])
            ? CarbonImmutable::parse($validated['started_at'])->setTimezone(config('app.timezone'))
            : null;
        if ($startedAt?->isFuture()) {
            $startedAt = CarbonImmutable::now();
        }
        $session = $this->openTableSession->handle($table, $actor, $startedAt);

        return [
            'table_id' => (int) $session->table_id,
            'table_session_id' => (int) $session->getKey(),
            'order_id' => (int) $session->order->getKey(),
            'status' => $session->status->value,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function addItems(Store $store, User $actor, array $payload): array
    {
        $validated = Validator::make($payload, [
            'order_id' => ['required', 'integer'],
            'items' => ['required', 'array', 'min:1'],
        ])->validate();
        $order = Order::query()->where('store_id', $store->getKey())->findOrFail($validated['order_id']);
        $order = $this->addOrderItems->handle($order, $actor, $validated['items']);

        return [
            'order_id' => (int) $order->getKey(),
            'status' => $order->status->value,
            'total' => (int) round((float) $order->total),
            'item_count' => (int) $order->items->sum('quantity'),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function updateItem(Store $store, User $actor, array $payload): array
    {
        $validated = Validator::make($payload, [
            'order_item_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer'],
            'notes' => ['nullable', 'string'],
            'unit_price' => ['sometimes', 'integer', 'min:0', 'max:999999999'],
        ])->validate();
        $item = OrderItem::query()->where('store_id', $store->getKey())->findOrFail($validated['order_item_id']);
        $item = $this->updateOrderItem->handle(
            $item,
            $actor,
            $validated['quantity'],
            $validated['notes'] ?? null,
            isset($validated['unit_price']) ? (int) $validated['unit_price'] : null,
        );

        return [
            'order_item_id' => (int) $item->getKey(),
            'order_id' => (int) $item->order_id,
            'quantity' => $item->quantity,
            'notes' => $item->notes,
            'unit_price' => (int) round((float) $item->unit_price),
            'order_total' => (int) round((float) $item->order->fresh()->total),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function createKitchenTicket(Store $store, User $actor, array $payload): array
    {
        $validated = Validator::make($payload, [
            'order_id' => ['required', 'integer'],
            'printer_id' => ['required', 'integer'],
        ])->validate();
        $order = Order::query()->where('store_id', $store->getKey())->findOrFail($validated['order_id']);
        $printer = Printer::query()->where('store_id', $store->getKey())->findOrFail($validated['printer_id']);
        $job = $this->createKitchenPrintJob->handle($order, $printer, $actor);

        return [
            'print_job_id' => (int) $job->getKey(),
            'print_type' => $job->print_type->value,
            'status' => $job->status->value,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function checkout(Store $store, User $actor, array $payload, string $commandId): array
    {
        $validated = Validator::make($payload, [
            'order_id' => ['required', 'integer'],
            'receipt_printer_id' => ['nullable', 'integer'],
            'payment_method' => ['required', 'string'],
            'allow_unprinted_kitchen_items' => ['sometimes', 'boolean'],
        ])->validate();
        $paymentMethod = PaymentMethod::tryFrom($validated['payment_method']);

        if (! $paymentMethod) {
            throw ValidationException::withMessages(['payment_method' => 'Phương thức thanh toán không được hỗ trợ.']);
        }

        $order = Order::query()->where('store_id', $store->getKey())->findOrFail($validated['order_id']);
        $printer = isset($validated['receipt_printer_id'])
            ? Printer::query()->where('store_id', $store->getKey())->findOrFail($validated['receipt_printer_id'])
            : null;
        $result = $this->checkoutAndQueueReceipt->handle(
            $order,
            $actor,
            $printer,
            $paymentMethod,
            $commandId,
            (bool) ($validated['allow_unprinted_kitchen_items'] ?? false),
        );

        return [
            'payment' => [
                'id' => (int) $result['payment']->getKey(),
                'status' => $result['payment']->status->value,
                'amount' => (int) round((float) $result['payment']->amount),
            ],
            'receipt_print_job' => $result['receiptPrintJob'] ? [
                'id' => (int) $result['receiptPrintJob']->getKey(),
                'status' => $result['receiptPrintJob']->status->value,
            ] : null,
            'receipt_error' => $result['receiptError'],
        ];
    }

    private function validateEnvelope(string $commandId, string $deviceId, Store $store, User $actor): void
    {
        $validator = Validator::make([
            'command_id' => $commandId,
            'device_id' => $deviceId,
        ], [
            'command_id' => ['required', 'uuid'],
            'device_id' => ['required', 'uuid'],
        ]);
        $validator->validate();

        if (! $actor->canAccessStore((int) $store->getKey())) {
            throw new AuthorizationException('Tài khoản không được phép gửi command tới cửa hàng này.');
        }
    }

    private function ensureEnvelopeMatches(
        PosCommand $command,
        Store $store,
        string $deviceId,
        User $actor,
        PosCommandType $type,
        string $payloadHash,
    ): void {
        $matches = (int) $command->store_id === (int) $store->getKey()
            && $command->device_id === $deviceId
            && (int) $command->user_id === (int) $actor->getKey()
            && $command->type === $type
            && hash_equals($command->payload_hash, $payloadHash);

        if (! $matches) {
            throw ValidationException::withMessages([
                'command_id' => 'UUID command đã được dùng với store, thiết bị, người dùng, loại hoặc payload khác.',
            ]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function payloadHash(array $payload): string
    {
        try {
            return hash('sha256', json_encode(
                $this->canonicalize($payload),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
            ));
        } catch (JsonException) {
            throw ValidationException::withMessages(['payload' => 'Payload command không thể chuẩn hóa thành JSON.']);
        }
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value);

        return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
    }

    private function isExpectedException(Throwable $exception): bool
    {
        return $exception instanceof ValidationException
            || $exception instanceof AuthorizationException
            || $exception instanceof ModelNotFoundException;
    }

    private function errorMessage(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            $message = collect($exception->errors())->flatten()->first();

            if (is_string($message) && $message !== '') {
                return $message;
            }
        }

        if ($exception instanceof AuthorizationException) {
            return 'Tài khoản không có quyền thực hiện command này.';
        }

        if ($exception instanceof ModelNotFoundException) {
            return 'Không tìm thấy dữ liệu thuộc cửa hàng cho command này.';
        }

        return 'Không thể xử lý command do lỗi hệ thống.';
    }
}
