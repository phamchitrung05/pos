<?php

namespace App\Actions\Pos;

use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Events\PosStateChanged;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Pos\PosActivityLogger;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Mở một phiên phục vụ và order rỗng cho bàn trong cùng một transaction. */
final class OpenTableSession
{
    public function __construct(private readonly PosActivityLogger $activityLogger) {}

    /**
     * Khóa dòng bàn trước khi kiểm tra phiên đang mở để hai thiết bị không thể
     * cùng lúc tạo hai phiên cho một bàn. Quyền được kiểm tra bằng Laravel Gate
     * để action dùng an toàn từ cả Filament lẫn API Tauri sau này.
     */
    public function handle(DiningTable $table, User $actor, ?CarbonInterface $startedAt = null): TableSession
    {
        try {
            $session = DB::transaction(function () use ($table, $actor, $startedAt): TableSession {
                /** @var DiningTable $lockedTable */
                $lockedTable = DiningTable::query()
                    ->lockForUpdate()
                    ->findOrFail($table->getKey());

                Gate::forUser($actor)->authorize('update', $lockedTable);
                Gate::forUser($actor)->authorize('create', TableSession::class);
                Gate::forUser($actor)->authorize('create', Order::class);

                /** @var TableSession|null $openSession */
                $openSession = $lockedTable->sessions()
                    ->where('status', TableSessionStatus::Open->value)
                    ->latest('id')
                    ->first();

                if ($openSession) {
                    // Thiết bị thứ hai dùng lại phiên hiện tại để cùng thao tác,
                    // nhưng database vẫn chỉ duy trì một session/order cho bàn.
                    return $openSession->load(['table', 'order']);
                }

                // Khóa audit lấy trực tiếp từ tài khoản đã xác thực, không nhận từ payload client.
                $session = new TableSession;
                $session->forceFill([
                    'store_id' => $lockedTable->store_id,
                    'table_id' => $lockedTable->getKey(),
                    // Client có thể giữ nháp offline; dùng mốc món đầu tiên để thời gian phục vụ không bị đặt lại lúc sync.
                    'start_time' => $startedAt ?? now(),
                    'end_time' => null,
                    'status' => TableSessionStatus::Open,
                    'opened_by' => $actor->getKey(),
                    'closed_by' => null,
                ]);
                $session->save();

                // Mỗi lần mở bàn khởi tạo một order chính; các lần gọi thêm sẽ nối vào order này.
                $order = new Order;
                $order->forceFill([
                    'store_id' => $lockedTable->store_id,
                    'table_session_id' => $session->getKey(),
                    'status' => OrderStatus::Open,
                    'total' => '0.00',
                    'created_by' => $actor->getKey(),
                ]);
                $order->save();
                $this->activityLogger->sessionOpened($session, $actor);

                return $session->refresh()->load(['table', 'order']);
            });
        } catch (QueryException $exception) {
            // SQLite không hỗ trợ lockForUpdate; unique sentinel vẫn chặn dữ liệu
            // trùng và nhánh này chuyển lỗi database thành thông báo nghiệp vụ.
            $openSession = TableSession::query()
                ->where('table_id', $table->getKey())
                ->where('status', TableSessionStatus::Open->value)
                ->latest('id')
                ->first();

            if ($openSession) {
                // Request thua cuộc đua trả về phiên vừa được thiết bị kia tạo,
                // giúp UI tiếp tục order mà không phải hiển thị lỗi kỹ thuật.
                $session = $openSession->load(['table', 'order']);

                PosStateChanged::dispatch((int) $session->store_id, (int) $session->table_id, 'session.opened');

                return $session;
            }

            throw $exception;
        }

        // Dispatch nằm sau DB::transaction nên client không thể đọc projection chưa commit.
        PosStateChanged::dispatch((int) $session->store_id, (int) $session->table_id, 'session.opened');

        return $session;
    }
}
