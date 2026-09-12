<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sửa schema của các database đã ghi nhận migration workflow nhưng migration đó từng dừng giữa chừng.
 *
 * MySQL có thể giữ các ALTER TABLE đầu tiên dù một ALTER sau thất bại. Khi đó Laravel vẫn có thể bị
 * đánh dấu migration đã chạy trong một lần thao tác thủ công, tạo ra trạng thái opened_by đã tồn tại
 * nhưng active_table_id và unique order còn thiếu. Migration này chỉ bổ sung đúng phần thiếu và an
 * toàn khi chạy trên database mới đã có đầy đủ constraint.
 */
return new class extends Migration
{
    /** Khôi phục các cột/index bảo vệ quy tắc một phiên mở và một order cho mỗi phiên bàn. */
    public function up(): void
    {
        $this->ensureExistingDataIsValid();

        if (! Schema::hasColumn('table_sessions', 'active_table_id')) {
            Schema::table('table_sessions', function (Blueprint $table): void {
                // Sentinel chỉ trỏ tới bàn khi session đang open; phiên đã đóng sẽ giữ giá trị null.
                $table->foreignId('active_table_id')
                    ->nullable()
                    ->after('table_id')
                    ->constrained('dining_table')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            });
        }

        // Backfill trước khi tạo unique index để constraint phản ánh đúng các phiên đang hoạt động.
        DB::table('table_sessions')->update([
            'active_table_id' => DB::raw("CASE WHEN status = 'open' THEN table_id ELSE NULL END"),
        ]);

        if (! Schema::hasIndex('table_sessions', ['active_table_id'], 'unique')) {
            Schema::table('table_sessions', function (Blueprint $table): void {
                $table->unique('active_table_id');
            });
        }

        if (! Schema::hasIndex('orders', ['table_session_id'], 'unique')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->unique('table_session_id');
            });
        }
    }

    /**
     * Không gỡ constraint khi rollback migration sửa lỗi.
     *
     * Các constraint này vốn thuộc migration workflow trước đó và model đang phụ thuộc trực tiếp vào
     * chúng. Gỡ ra sẽ tái tạo database hỏng; migration gốc chịu trách nhiệm xóa khi rollback toàn bộ.
     */
    public function down(): void {}

    /** Dừng migration với thông báo rõ ràng nếu dữ liệu hiện tại không thể áp unique constraint. */
    private function ensureExistingDataIsValid(): void
    {
        $hasDuplicateOpenSession = DB::table('table_sessions')
            ->select('table_id')
            ->where('status', 'open')
            ->groupBy('table_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicateOpenSession) {
            throw new RuntimeException('Không thể sửa schema: một bàn đang có nhiều phiên open.');
        }

        $hasMultipleOrders = DB::table('orders')
            ->select('table_session_id')
            ->groupBy('table_session_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasMultipleOrders) {
            throw new RuntimeException('Không thể sửa schema: một phiên bàn đang có nhiều order.');
        }
    }
};
