<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bổ sung dữ liệu cần thiết để thực thi luồng mở bàn, gọi món, in bếp và thanh toán.
 *
 * Các khóa người thao tác đều nullable để giữ được dữ liệu cũ và dùng
 * `nullOnDelete()` nhằm bảo toàn lịch sử bán hàng khi một tài khoản bị xóa.
 */
return new class extends Migration
{
    /** Thêm cột audit, dấu mốc in bếp và khóa chống tạo thanh toán trùng. */
    public function up(): void
    {
        $this->ensureWorkflowDataCanBeConstrained();

        Schema::table('table_sessions', function (Blueprint $table): void {
            // Ghi người mở và đóng phiên để chủ cửa hàng truy vết thao tác theo ca.
            $table->foreignId('opened_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->after('opened_by')->constrained('users')->nullOnDelete();

            // Giá trị chỉ tồn tại khi session open; unique bảo đảm một bàn không có hai phiên mở.
            $table->foreignId('active_table_id')
                ->nullable()
                ->after('table_id')
                ->constrained('dining_table')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->unique('active_table_id');
        });

        // Backfill dấu khóa cho phiên đang mở sau khi cột nullable đã tồn tại.
        DB::table('table_sessions')
            ->where('status', 'open')
            ->update(['active_table_id' => DB::raw('table_id')]);

        Schema::table('orders', function (Blueprint $table): void {
            // Người tạo order được lưu tại server, không lấy tùy ý từ payload client.
            $table->foreignId('created_by')->nullable()->after('store_id')->constrained('users')->nullOnDelete();

            // Nghiệp vụ POS hiện tại dùng đúng một order chính trong mỗi phiên bàn.
            $table->unique('table_session_id');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            // Số lượng đã đưa vào phiếu bếp giúp lần in sau chỉ chứa phần tăng thêm.
            $table->unsignedInteger('kitchen_printed_quantity')->default(0)->after('quantity');
        });

        Schema::table('payments', function (Blueprint $table): void {
            // Payment method có giá trị mặc định để backfill dữ liệu thanh toán cũ.
            $table->string('payment_method', 50)->default('cash')->after('amount');
            $table->foreignId('received_by')->nullable()->after('store_id')->constrained('users')->nullOnDelete();

            // UUID do web/app gửi lên cho phép retry request mà không ghi hai giao dịch.
            $table->uuid('client_request_id')->nullable()->after('paid_at')->unique();
        });

        // Máy in cũ chưa có loại được xem là máy in hóa đơn để model luôn cast được enum.
        DB::table('printers')->whereNull('printer_type')->update(['printer_type' => 'receipt']);

        Schema::table('printers', function (Blueprint $table): void {
            $table->string('printer_type', 50)
                ->default('receipt')
                ->nullable(false)
                ->comment('Loại máy in: receipt, kitchen hoặc label')
                ->change();
        });

        // Chuẩn hóa tên trạng thái cũ trước khi Eloquent bắt đầu cast bằng PrintJobStatus.
        DB::table('print_jobs')->where('status', 'success')->update(['status' => 'printed']);
        DB::table('print_jobs')->where('status', 'canceled')->update(['status' => 'cancelled']);
    }

    /** Gỡ các cột mới và phục hồi tên trạng thái cũ khi rollback. */
    public function down(): void
    {
        DB::table('print_jobs')->where('status', 'printed')->update(['status' => 'success']);
        DB::table('print_jobs')->where('status', 'cancelled')->update(['status' => 'canceled']);

        Schema::table('printers', function (Blueprint $table): void {
            $table->string('printer_type', 50)
                ->nullable()
                ->default(null)
                ->comment('Ví dụ: receipt, kitchen, label')
                ->change();
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['client_request_id']);
            $table->dropConstrainedForeignId('received_by');
            $table->dropColumn(['payment_method', 'client_request_id']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('kitchen_printed_quantity');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['table_session_id']);
            $table->dropConstrainedForeignId('created_by');
        });

        Schema::table('table_sessions', function (Blueprint $table): void {
            // Gỡ foreign key trước vì MySQL không cho xóa index đang phục vụ ràng buộc này.
            $table->dropForeign(['active_table_id']);
            $table->dropUnique(['active_table_id']);
            $table->dropColumn('active_table_id');
            $table->dropConstrainedForeignId('closed_by');
            $table->dropConstrainedForeignId('opened_by');
        });
    }

    /**
     * Dừng trước khi thay đổi schema nếu dữ liệu cũ vi phạm quy tắc POS mới.
     *
     * Kiểm tra sớm giúp migration không rơi vào trạng thái đã thêm một phần cột
     * trên MySQL khi unique index thất bại ở giữa quá trình triển khai.
     */
    private function ensureWorkflowDataCanBeConstrained(): void
    {
        $this->ensureColumnContainsOnly('table_sessions', 'status', ['open', 'closed', 'cancelled']);
        $this->ensureColumnContainsOnly('orders', 'status', ['open', 'paid', 'cancelled']);
        $this->ensureColumnContainsOnly('payments', 'status', ['pending', 'completed', 'failed', 'refunded']);
        $this->ensureColumnContainsOnly('printers', 'printer_type', ['receipt', 'kitchen', 'label'], allowNull: true);
        $this->ensureColumnContainsOnly('print_jobs', 'print_type', ['receipt', 'kitchen', 'label']);
        $this->ensureColumnContainsOnly('print_jobs', 'status', ['pending', 'printing', 'success', 'printed', 'failed', 'canceled', 'cancelled']);

        if (DB::table('product')->where('price', '<=', 0)->exists()) {
            throw new RuntimeException('Không thể nâng cấp: đang có sản phẩm mang giá bán nhỏ hơn hoặc bằng 0.');
        }

        $tableWithMultipleOpenSessions = DB::table('table_sessions')
            ->select('table_id')
            ->where('status', 'open')
            ->groupBy('table_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($tableWithMultipleOpenSessions) {
            throw new RuntimeException('Không thể nâng cấp: đang có bàn chứa nhiều hơn một phiên open.');
        }

        $sessionWithMultipleOrders = DB::table('orders')
            ->select('table_session_id')
            ->groupBy('table_session_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($sessionWithMultipleOrders) {
            throw new RuntimeException('Không thể nâng cấp: đang có phiên bàn chứa nhiều hơn một order.');
        }
    }

    /**
     * Xác nhận dữ liệu chuỗi cũ nằm trong miền enum trước khi model bật enum cast.
     * Lỗi được báo kèm bảng/cột để người vận hành có thể sửa dữ liệu rõ ràng.
     *
     * @param  array<int, string>  $allowedValues
     */
    private function ensureColumnContainsOnly(string $table, string $column, array $allowedValues, bool $allowNull = false): void
    {
        $invalidValues = DB::table($table)
            ->when(! $allowNull, fn ($query) => $query->whereNotNull($column))
            ->whereNotIn($column, $allowedValues)
            ->distinct()
            ->pluck($column)
            ->filter(fn ($value): bool => $value !== null || ! $allowNull)
            ->values();

        if ($invalidValues->isNotEmpty()) {
            throw new RuntimeException(
                "Không thể nâng cấp: [{$table}.{$column}] chứa giá trị không hợp lệ: ".$invalidValues->implode(', '),
            );
        }
    }
};
