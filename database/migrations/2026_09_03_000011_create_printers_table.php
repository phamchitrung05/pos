<?php

/**
 * Migration: Tạo bảng `printers` (Máy in).
 *
 * Lưu cấu hình các máy in của chi nhánh: máy in hóa đơn, máy in bếp,
 * máy in tem/nhãn... kết nối qua mạng LAN (IP + port).
 *
 * Quan hệ:
 * - printers.store_id     -> store.id    (máy in thuộc chi nhánh nào)
 * - print_jobs.printer_id -> printers.id (được tham chiếu bởi lệnh in)
 *
 * Schema nguồn: file JSON thiết kế CSDL (database diagram).
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chạy migration: tạo bảng `printers`.
     */
    public function up(): void
    {
        Schema::create('printers', function (Blueprint $table) {
            // Khóa chính, tự tăng (BIGINT UNSIGNED AUTO_INCREMENT)
            $table->id();

            // Chi nhánh sở hữu máy in
            // cascadeOnDelete: xóa chi nhánh thì xóa luôn các máy in của chi nhánh
            $table->foreignId('store_id')
                ->comment('Chi nhánh sở hữu máy in')
                ->constrained('store')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Tên máy in để nhận diện (VD: "Máy in quầy", "Máy in bếp")
            $table->string('name')->comment('Tên máy in');

            // Loại máy in quyết định nội dung in: receipt (hóa đơn),
            // kitchen (bếp), label (tem/nhãn)
            $table->string('printer_type', 50)->nullable()
                ->comment('Ví dụ: receipt, kitchen, label');

            // Địa chỉ IP của máy in mạng, giới hạn 45 ký tự
            // (vừa đủ cho địa chỉ IPv6 dài nhất)
            $table->string('ip_address', 45)->nullable()
                ->comment('Địa chỉ IP máy in');

            // Cổng kết nối mạng của máy in (thường 9100, 515, 631...)
            $table->integer('port')->nullable()->comment('Cổng kết nối');

            // Cờ bật/tắt sử dụng máy in, mặc định là đang bật
            $table->boolean('is_active')->default(true)->comment('Máy in còn sử dụng hay không');

            // created_at, updated_at do Eloquent tự quản lý
            $table->timestamps();
        });
    }

    /**
     * Quay lại migration: xóa bảng `printers`.
     * Phải chạy sau khi bảng `print_jobs` (bảng tham chiếu tới đây) đã bị xóa.
     */
    public function down(): void
    {
        Schema::dropIfExists('printers');
    }
};
