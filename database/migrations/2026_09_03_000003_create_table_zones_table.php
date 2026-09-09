<?php

/**
 * Migration: Tạo bảng `table_zones` (Khu vực bàn).
 *
 * Lưu danh sách khu vực trong mỗi chi nhánh để phân loại bàn ăn,
 * ví dụ: "trong nhà", "ngoài trời", "tầng 2"...
 *
 * Quan hệ:
 * - table_zones.store_id -> store.id        (mỗi khu vực thuộc một chi nhánh)
 * - dining_table.zone_id -> table_zones.id  (được tham chiếu bởi bảng bàn)
 *
 * Schema nguồn: file JSON thiết kế CSDL (database diagram).
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chạy migration: tạo bảng `table_zones`.
     */
    public function up(): void
    {
        Schema::create('table_zones', function (Blueprint $table) {
            // Khóa chính, tự tăng (BIGINT UNSIGNED AUTO_INCREMENT)
            $table->id();

            // Chi nhánh sở hữu khu vực này
            // cascadeOnUpdate: khi `store.id` đổi thì `store_id` đổi theo
            // cascadeOnDelete: xóa chi nhánh thì xóa luôn các khu vực thuộc chi nhánh đó
            $table->foreignId('store_id')
                ->comment('Chi nhánh sở hữu khu vực')
                ->constrained('store')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Tên khu vực, giới hạn 100 ký tự (VD: "Trong nhà", "Ngoài trời")
            $table->string('name', 100)->comment('Tên khu vực, ví dụ: trong nhà, ngoài trời');

            // Cờ bật/tắt hiển thị khu vực, mặc định là bật
            $table->boolean('is_active')->default(true)->comment('Khu vực còn sử dụng hay không');

            // created_at, updated_at do Eloquent tự quản lý
            $table->timestamps();
        });
    }

    /**
     * Quay lại migration: xóa bảng `table_zones`.
     * Phải chạy sau khi bảng `dining_table` (bảng tham chiếu tới đây) đã bị xóa.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_zones');
    }
};
