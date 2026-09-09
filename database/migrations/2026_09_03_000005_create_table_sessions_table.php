<?php

/**
 * Migration: Tạo bảng `table_sessions` (Phiên sử dụng bàn).
 *
 * Mỗi lần khách ngồi vào một bàn và gọi món được coi là một "phiên bàn".
 * Một bàn có thể có nhiều phiên (theo thời gian), mỗi phiên có thể có
 * nhiều đơn hàng (orders) trong suốt thời gian khách ngồi.
 *
 * Quan hệ:
 * - table_sessions.table_id -> dining_table.id (phiên thuộc về bàn nào)
 * - orders.table_session_id -> table_sessions.id (được tham chiếu bởi đơn hàng)
 *
 * Schema nguồn: file JSON thiết kế CSDL (database diagram).
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chạy migration: tạo bảng `table_sessions`.
     */
    public function up(): void
    {
        Schema::create('table_sessions', function (Blueprint $table) {
            // Khóa chính, tự tăng (BIGINT UNSIGNED AUTO_INCREMENT)
            $table->id();

            // Bàn mà phiên này diễn ra
            // cascadeOnDelete: xóa bàn thì xóa luôn các phiên của bàn đó
            $table->foreignId('table_id')
                ->comment('Bàn mà phiên này diễn ra')
                ->constrained('dining_table')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Thời điểm khách vào ngồi / phiên bắt đầu (NULL = chưa bắt đầu)
            $table->timestamp('start_time')->nullable()->comment('Thời điểm bắt đầu phiên');

            // Thời điểm khách trả bàn / phiên kết thúc (NULL = phiên đang mở)
            $table->timestamp('end_time')->nullable()->comment('Thời điểm kết thúc phiên');

            // Trạng thái phiên: open (đang mở), closed (đã đóng), cancelled (đã hủy)
            // Mặc định là "open" khi tạo phiên mới
            $table->string('status', 50)->default('open')->comment('Ví dụ: open, closed, cancelled');

            // created_at, updated_at do Eloquent tự quản lý
            $table->timestamps();
        });
    }

    /**
     * Quay lại migration: xóa bảng `table_sessions`.
     * Phải chạy sau khi bảng `orders` (bảng tham chiếu tới đây) đã bị xóa.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_sessions');
    }
};
