<?php

namespace App\Actions\Pos;

use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Mở một phiên phục vụ và order rỗng cho bàn trong cùng một transaction. */
final class OpenTableSession
{
    /**
     * Khóa dòng bàn trước khi kiểm tra phiên đang mở để hai thiết bị không thể
     * cùng lúc tạo hai phiên cho một bàn. Quyền được kiểm tra bằng Laravel Gate
     * để action dùng an toàn từ cả Filament lẫn API Tauri sau này.
     */
    public function handle(DiningTable $table, User $actor): TableSession
    {
        try {
            return DB::transaction(function () use ($table, $actor): TableSession {
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
                    'start_time' => now(),
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
                return $openSession->load(['table', 'order']);
            }

            throw $exception;
        }
    }
}
