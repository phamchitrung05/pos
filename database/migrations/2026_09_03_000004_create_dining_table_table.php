<?php

/**
 * Migration: Tạo bảng `dining_table` (Bàn ăn).
 *
 * Lưu danh sách bàn của từng chi nhánh, có thể gán vào một khu vực
 * (table_zones) để hiển thị sơ đồ bàn theo nhóm.
 *
 * Quan hệ:
 * - dining_table.store_id -> store.id        (bàn thuộc chi nhánh nào)
 * - dining_table.zone_id  -> table_zones.id  (bàn nằm trong khu vực nào)
 * - table_sessions.table_id -> dining_table.id (được tham chiếu bởi phiên bàn)
 *
 * Schema nguồn: file JSON thiết kế CSDL (database diagram).
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chạy migration: tạo bảng `dining_table`.
     */
    public function up(): void
    {
        Schema::create('dining_table', function (Blueprint $table) {
            // Khóa chính, tự tăng (BIGINT UNSIGNED AUTO_INCREMENT)
            $table->id();

            // Chi nhánh sở hữu bàn
            // cascadeOnDelete: xóa chi nhánh thì xóa luôn các bàn của chi nhánh đó
            $table->foreignId('store_id')
                ->comment('Chi nhánh sở hữu bàn')
                ->constrained('store')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Khu vực chứa bàn - cho phép NULL vì bàn có thể chưa phân khu
            // cascadeOnDelete: xóa khu vực thì các bàn thuộc khu vực đó cũng bị xóa
            // (vẫn giữ được khi không gán khu vực nhờ nullable)
            $table->foreignId('zone_id')
                ->nullable()
                ->comment('Khu vực bàn')
                ->constrained('table_zones')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Tên/số hiệu bàn hiển thị cho nhân viên (VD: "Bàn 01", "A3")
            $table->string('name')->comment('Tên hoặc số hiệu bàn');

            // created_at, updated_at do Eloquent tự quản lý
            $table->timestamps();
        });
    }

    /**
     * Quay lại migration: xóa bảng `dining_table`.
     * Phải chạy sau khi bảng `table_sessions` (bảng tham chiếu tới đây) đã bị xóa.
     */
    public function down(): void
    {
        Schema::dropIfExists('dining_table');
    }
};
