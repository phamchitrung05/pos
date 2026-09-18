<?php

namespace App\Http\Controllers\Api;

use App\Actions\Pos\ProcessPosCommand;
use App\Enums\PosCommandStatus;
use App\Enums\PosCommandType;
use App\Http\Controllers\Controller;
use App\Http\Resources\PosCommandResource;
use App\Models\DiningTable;
use App\Models\PosCommand;
use App\Models\Store;
use App\Models\User;
use App\Queries\Pos\TableMapReadModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Chuyển request đơn hoặc batch thành command idempotent ở application layer. */
final class PosCommandController extends Controller
{
    public function store(Request $request, ProcessPosCommand $processor): JsonResponse
    {
        [$user, $store, $deviceId] = $this->context($request);
        $validated = $request->validate([
            'id' => ['required', 'uuid'],
            'device_id' => ['required', 'uuid', Rule::in([$deviceId])],
            'type' => ['required', Rule::enum(PosCommandType::class)],
            'payload' => ['required', 'array'],
        ]);
        $command = $processor->handle(
            $validated['id'],
            $store,
            $deviceId,
            $user,
            PosCommandType::from($validated['type']),
            $validated['payload'],
        );

        return response()->json(
            ['data' => PosCommandResource::make($command)->resolve($request)],
            $command->status === PosCommandStatus::Failed ? 422 : 200,
        );
    }

    public function sync(Request $request, ProcessPosCommand $processor, TableMapReadModel $readModel): JsonResponse
    {
        [$user, $store, $deviceId] = $this->context($request);
        $validated = $request->validate([
            'device_id' => ['required', 'uuid', Rule::in([$deviceId])],
            'commands' => ['present', 'array', 'max:50'],
            'commands.*.id' => ['required', 'uuid'],
            'commands.*.type' => ['required', Rule::enum(PosCommandType::class)],
            'commands.*.payload' => ['required', 'array'],
        ]);
        Gate::forUser($user)->authorize('viewAny', DiningTable::class);
        $commands = collect($validated['commands'])
            ->map(function (array $commandData) use ($processor, $store, $deviceId, $user, $request): array {
                $command = $processor->handle(
                    $commandData['id'],
                    $store,
                    $deviceId,
                    $user,
                    PosCommandType::from($commandData['type']),
                    $commandData['payload'],
                );

                return PosCommandResource::make($command)->resolve($request);
            })
            ->values()
            ->all();

        $changedTableIds = collect($validated['commands'])
            ->map(fn (array $command): ?int => isset($command['payload']['table_id'])
                ? (int) $command['payload']['table_id']
                : null)
            ->filter(fn (?int $tableId): bool => $tableId !== null && $tableId > 0)
            ->unique()
            ->values();

        return response()->json([
            'data' => [
                'commands' => $commands,
                // Sync chỉ trả các bàn liên quan đến batch; snapshot toàn store
                // chỉ được dùng lúc khởi tạo, refresh thủ công hoặc đối soát.
                'changed_tables' => $changedTableIds
                    ->map(fn (int $tableId): ?array => $readModel->selectedTable($store, $tableId))
                    ->filter()
                    ->values()
                    ->all(),
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Đối soát read-only: trả sự thật phía server cho các UUID thiết bị đang
     * nghi ngờ (failed local, syncing kẹt). UUID vắng mặt nghĩa là command
     * chưa từng tới server và client được phép gửi lại với đúng UUID đó.
     */
    public function reconcile(Request $request, TableMapReadModel $readModel): JsonResponse
    {
        [$user, $store, $deviceId] = $this->context($request);
        $validated = $request->validate([
            'device_id' => ['required', 'uuid', Rule::in([$deviceId])],
            'command_ids' => ['present', 'array', 'max:200'],
            'command_ids.*' => ['required', 'uuid'],
        ]);
        Gate::forUser($user)->authorize('viewAny', DiningTable::class);

        $commands = PosCommand::query()
            ->where('store_id', $store->getKey())
            ->where('device_id', $deviceId)
            ->whereIn('id', array_values(array_unique($validated['command_ids'])))
            ->get();

        // Chỉ lệnh completed mới làm bàn thay đổi; failed được rollback nên
        // không cần patch projection. result.table_id có sẵn từ application layer.
        $changedTableIds = $commands
            ->filter(fn (PosCommand $command): bool => $command->status === PosCommandStatus::Completed)
            ->map(fn (PosCommand $command): ?int => isset($command->result['table_id'])
                ? (int) $command->result['table_id']
                : null)
            ->filter(fn (?int $tableId): bool => $tableId !== null && $tableId > 0)
            ->unique()
            ->values();

        return response()->json([
            'data' => [
                'commands' => PosCommandResource::collection($commands)->resolve($request),
                'changed_tables' => $changedTableIds
                    ->map(fn (int $tableId): ?array => $readModel->selectedTable($store, $tableId))
                    ->filter()
                    ->values()
                    ->all(),
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }

    /** @return array{User, Store, string} */
    private function context(Request $request): array
    {
        $user = $request->user();
        $store = $request->attributes->get('pos_store');
        $deviceId = $request->attributes->get('pos_device_id');
        abort_unless($user instanceof User && $store instanceof Store && is_string($deviceId), 403);

        return [$user, $store, $deviceId];
    }
}
