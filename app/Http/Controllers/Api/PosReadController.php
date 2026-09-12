<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Printer;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Store;
use App\Models\TableSession;
use App\Models\TableZone;
use App\Models\User;
use App\Queries\Pos\PosApiReadModel;
use App\Queries\Pos\TableMapReadModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Các projection đọc tenant-safe cho Tauri; không thực hiện ghi nghiệp vụ. */
final class PosReadController extends Controller
{
    public function bootstrap(Request $request, PosApiReadModel $readModel): JsonResponse
    {
        [$user, $store] = $this->context($request);

        foreach ([TableZone::class, ProductGroup::class, Product::class, Printer::class] as $model) {
            Gate::forUser($user)->authorize('viewAny', $model);
        }

        return response()->json(['data' => $readModel->bootstrap($store, $user)]);
    }

    public function tables(Request $request, TableMapReadModel $readModel): JsonResponse
    {
        [$user, $store] = $this->context($request);
        $validated = $request->validate([
            'zone' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', Rule::in(['all', 'empty', 'occupied'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        foreach ([DiningTable::class, TableSession::class, Order::class] as $model) {
            Gate::forUser($user)->authorize('viewAny', $model);
        }

        return response()->json([
            'data' => $readModel->overview(
                $store,
                $validated['zone'] ?? 'all',
                $validated['status'] ?? 'all',
                $validated['search'] ?? '',
            ),
        ]);
    }

    /**
     * Trả projection của đúng một bàn để client realtime patch cục bộ.
     *
     * Endpoint này cố ý dùng cùng read model với snapshot toàn cửa hàng để
     * tránh việc sơ đồ bàn và panel chi tiết diễn giải trạng thái khác nhau.
     */
    public function table(Request $request, int $table, TableMapReadModel $readModel): JsonResponse
    {
        [$user, $store] = $this->context($request);
        Gate::forUser($user)->authorize('viewAny', DiningTable::class);

        $tableData = $readModel->selectedTable($store, $table);
        abort_unless($tableData !== null, 404);

        return response()->json(['data' => $tableData]);
    }

    public function orders(Request $request, PosApiReadModel $readModel): JsonResponse
    {
        [$user, $store] = $this->context($request);
        Gate::forUser($user)->authorize('viewAny', Order::class);

        return response()->json(['data' => $readModel->todayOrders($store)]);
    }

    public function order(Request $request, int $order, PosApiReadModel $readModel): JsonResponse
    {
        [$user, $store] = $this->context($request);
        $orderModel = Order::query()->where('store_id', $store->getKey())->findOrFail($order);
        Gate::forUser($user)->authorize('view', $orderModel);

        return response()->json(['data' => $readModel->order($orderModel)]);
    }

    /** @return array{User, Store} */
    private function context(Request $request): array
    {
        $user = $request->user();
        $store = $request->attributes->get('pos_store');
        abort_unless($user instanceof User && $store instanceof Store, 403);

        return [$user, $store];
    }
}
