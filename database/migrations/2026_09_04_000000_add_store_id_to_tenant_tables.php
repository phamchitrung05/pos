<?php

/**
 * Bổ sung khóa tenant trực tiếp cho các bảng nghiệp vụ để truy vấn theo cửa hàng an toàn và hiệu quả.
 *
 * Cột được tạo nullable trước để dữ liệu hiện hữu có thể nhận `store_id` từ quan hệ cha,
 * sau đó mới chuyển sang bắt buộc. Khóa ngoại chỉ bảo đảm cửa hàng tồn tại; mã ghi dữ liệu
 * vẫn phải lấy tenant từ ngữ cảnh đã xác thực, tuyệt đối không tin `store_id` do client gửi lên.
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Danh sách bảng và cột cha dùng để đặt khóa tenant đúng vị trí trong schema. */
    private const TENANT_COLUMNS = [
        'product' => 'product_group_id',
        'table_sessions' => 'table_id',
        'orders' => 'table_session_id',
        'order_items' => 'order_id',
        'payments' => 'order_id',
        'print_jobs' => 'printer_id',
    ];

    /**
     * Thêm, backfill và khóa chặt `store_id` để mọi bản ghi nghiệp vụ luôn thuộc một tenant.
     */
    public function up(): void
    {
        $this->addNullableTenantColumns();
        $this->backfillTenantColumns();
        $this->ensureTenantBackfillCompleted();
        $this->makeTenantColumnsRequired();
    }

    /**
     * Gỡ khóa ngoại trước cột theo thứ tự bảng con ngược lại, tránh vi phạm ràng buộc tenant.
     */
    public function down(): void
    {
        foreach (array_reverse(array_keys(self::TENANT_COLUMNS)) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('store_id');
            });
        }
    }

    /**
     * Tạo cột nullable để triển khai an toàn trên dữ liệu cũ, kèm FK xóa/cập nhật dây chuyền từ cửa hàng.
     */
    private function addNullableTenantColumns(): void
    {
        foreach (self::TENANT_COLUMNS as $tableName => $afterColumn) {
            Schema::table($tableName, function (Blueprint $table) use ($afterColumn) {
                $table->foreignId('store_id')
                    ->nullable()
                    ->after($afterColumn)
                    ->comment('Cửa hàng sở hữu bản ghi; phải lấy từ tenant đã xác thực')
                    ->constrained('store')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Sao chép tenant từ quan hệ cha bắt buộc; lệnh in lấy theo máy in vì đơn hàng có thể để trống.
     */
    private function backfillTenantColumns(): void
    {
        // Sản phẩm kế thừa tenant từ nhóm sản phẩm để không thể bị đưa sang thực đơn cửa hàng khác.
        DB::table('product')->whereNull('store_id')->update([
            'store_id' => DB::raw('(SELECT product_group.store_id FROM product_group WHERE product_group.id = product.product_group_id)'),
        ]);

        // Phiên bàn kế thừa tenant từ bàn ăn, là nguồn sở hữu đã được khóa ngoại bảo vệ.
        DB::table('table_sessions')->whereNull('store_id')->update([
            'store_id' => DB::raw('(SELECT dining_table.store_id FROM dining_table WHERE dining_table.id = table_sessions.table_id)'),
        ]);

        // Đơn hàng kế thừa tenant từ phiên bàn để giữ toàn bộ giao dịch trong cùng cửa hàng.
        DB::table('orders')->whereNull('store_id')->update([
            'store_id' => DB::raw('(SELECT table_sessions.store_id FROM table_sessions WHERE table_sessions.id = orders.table_session_id)'),
        ]);

        // Dòng món kế thừa tenant từ đơn hàng, không nhận tenant tùy ý từ dữ liệu client.
        DB::table('order_items')->whereNull('store_id')->update([
            'store_id' => DB::raw('(SELECT orders.store_id FROM orders WHERE orders.id = order_items.order_id)'),
        ]);

        // Thanh toán kế thừa tenant từ đơn hàng để tránh đối soát chéo cửa hàng.
        DB::table('payments')->whereNull('store_id')->update([
            'store_id' => DB::raw('(SELECT orders.store_id FROM orders WHERE orders.id = payments.order_id)'),
        ]);

        // Lệnh in kế thừa tenant từ máy in bắt buộc, kể cả khi lệnh không gắn với đơn hàng.
        DB::table('print_jobs')->whereNull('store_id')->update([
            'store_id' => DB::raw('(SELECT printers.store_id FROM printers WHERE printers.id = print_jobs.printer_id)'),
        ]);
    }

    /**
     * Dừng migration nếu quan hệ cha bị hỏng, thay vì giữ bản ghi không tenant gây rò rỉ dữ liệu.
     */
    private function ensureTenantBackfillCompleted(): void
    {
        foreach (array_keys(self::TENANT_COLUMNS) as $tableName) {
            if (DB::table($tableName)->whereNull('store_id')->exists()) {
                throw new RuntimeException("Không thể xác định tenant cho toàn bộ dữ liệu bảng [{$tableName}].");
            }
        }
    }

    /**
     * Chuyển cột sang NOT NULL bằng `change()`, Laravel 12 sẽ dựng lại bảng khi chạy trên SQLite.
     */
    private function makeTenantColumnsRequired(): void
    {
        foreach (array_keys(self::TENANT_COLUMNS) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('store_id')
                    ->nullable(false)
                    ->comment('Cửa hàng sở hữu bản ghi; phải lấy từ tenant đã xác thực')
                    ->change();
            });
        }
    }
};
