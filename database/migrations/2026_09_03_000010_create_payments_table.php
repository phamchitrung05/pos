<?php

/**
 * Migration: Tạo bảng `payments` (Thanh toán).
 *
 * Lưu các lần thanh toán cho đơn hàng. Một đơn có thể được thanh toán
 * nhiều lần (trả một phần, trả kết hợp tiền mặt + chuyển khoản...).
 *
 * Quan hệ:
 * - payments.order_id -> orders.id (thanh toán cho đơn hàng nào)
 *
 * Lưu ý: bảng này chỉ có `created_at` theo schema gốc, không có `updated_at`
 * (thao tác thanh toán thường chỉ ghi thêm chứ không sửa).
 *
 * Schema nguồn: file JSON thiết kế CSDL (database diagram).
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chạy migration: tạo bảng `payments`.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            // Khóa chính, tự tăng (BIGINT UNSIGNED AUTO_INCREMENT)
            $table->id();

            // Đơn hàng được thanh toán
            // cascadeOnDelete: xóa đơn thì xóa luôn các lần thanh toán của đơn
            $table->foreignId('order_id')
                ->comment('Đơn hàng được thanh toán')
                ->constrained('orders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Số tiền của lần thanh toán này
            // DECIMAL(10,2) để đảm bảo chính xác khi đối soát
            $table->decimal('amount', 10, 2)->comment('Số tiền thanh toán');

            // Trạng thái thanh toán: pending (chờ xử lý), completed (thành công),
            // failed (thất bại), refunded (đã hoàn tiền). Mặc định "pending"
            $table->string('status', 50)->default('pending')
                ->comment('Ví dụ: pending, completed, failed, refunded');

            // Thời điểm thanh toán thành công (NULL = chưa thanh toán xong)
            $table->timestamp('paid_at')->nullable()->comment('Thời điểm thanh toán thành công');

            // Thời điểm tạo bản ghi thanh toán
            $table->timestamp('created_at')->nullable()->comment('Thời điểm tạo bản ghi');
        });
    }

    /**
     * Quay lại migration: xóa bảng `payments`.
     * Bảng này không được bảng nào tham chiếu nên có thể xóa độc lập.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
