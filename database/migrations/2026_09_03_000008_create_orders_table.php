<?php

/**
 * Migration: Tạo bảng `orders` (Đơn hàng / Hóa đơn).
 *
 * Mỗi đơn hàng gắn với một phiên bàn (table_sessions). Trong một phiên,
 * khách có thể gọi nhiều lần => một phiên có thể có nhiều đơn hàng.
 *
 * Quan hệ:
 * - orders.table_session_id -> table_sessions.id (đơn thuộc phiên bàn nào)
 * - order_items.order_id -> orders.id    (chi tiết các món trong đơn)
 * - payments.order_id    -> orders.id    (các lần thanh toán cho đơn)
 * - print_jobs.order_id  -> orders.id    (các lệnh in liên quan đến đơn)
 *
 * Schema nguồn: file JSON thiết kế CSDL (database diagram).
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chạy migration: tạo bảng `orders`.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            // Khóa chính, tự tăng (BIGINT UNSIGNED AUTO_INCREMENT)
            $table->id();

            // Phiên bàn mà đơn hàng này thuộc về
            // cascadeOnDelete: xóa phiên thì xóa luôn các đơn trong phiên đó
            $table->foreignId('table_session_id')
                ->comment('Phiên bàn mà đơn hàng thuộc về')
                ->constrained('table_sessions')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Trạng thái đơn: open (đang phục vụ), paid (đã thanh toán), cancelled (đã hủy)
            // Mặc định là "open" khi tạo đơn mới
            $table->string('status', 50)->default('open')->comment('Ví dụ: open, paid, cancelled');

            // Tổng tiền của đơn, mặc định 0 - được cập nhật khi thêm/sửa món
            // DECIMAL(10,2) để đảm bảo chính xác khi tính tiền
            $table->decimal('total', 10, 2)->default(0)->comment('Tổng tiền đơn hàng');

            // Ghi chú chung của đơn (VD: "khách phục sinh nhật", cho phép NULL)
            $table->text('notes')->nullable()->comment('Ghi chú của đơn hàng');

            // created_at, updated_at do Eloquent tự quản lý
            // (created_at coincides với thời điểm tạo đơn theo schema gốc)
            $table->timestamps();
        });
    }

    /**
     * Quay lại migration: xóa bảng `orders`.
     * Phải chạy sau khi các bảng `order_items`, `payments`, `print_jobs`
     * (các bảng tham chiếu tới đây) đã bị xóa.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
