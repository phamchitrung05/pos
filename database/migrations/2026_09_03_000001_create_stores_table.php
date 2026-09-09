<?php

/**
 * Migration: Tạo bảng `store` (Cửa hàng / Chi nhánh).
 *
 * Đây là bảng gốc của hệ thống POS - mỗi bản ghi đại diện cho một chi nhánh
 * hoặc cửa hàng. Các bảng khác (users, product_group, table_zones, printers...)
 * đều tham chiếu về bảng này thông qua khóa ngoại `store_id`.
 *
 * Schema nguồn: file JSON thiết kế CSDL (database diagram).
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chạy migration: tạo bảng `store`.
     */
    public function up(): void
    {
        Schema::create('store', function (Blueprint $table) {
            // Khóa chính, tự tăng (BIGINT UNSIGNED AUTO_INCREMENT)
            $table->id();

            // Tên chi nhánh, bắt buộc nhập (VD: "Quán Nguyễn Huệ")
            $table->string('name')->comment('Tên chi nhánh');

            // Địa chỉ chi nhánh, kiểu TEXT vì địa chỉ có thể dài, cho phép NULL
            $table->text('address')->nullable()->comment('Địa chỉ chi nhánh');

            // Số điện thoại liên hệ, giới hạn 20 ký tự để vừa các đầu số dài nhất
            $table->string('phone', 20)->nullable()->comment('Số điện thoại');

            // Email liên hệ của chi nhánh
            $table->string('email')->nullable()->comment('Email liên hệ');

            // Giờ mở cửa dạng chuỗi tự do (VD: "08:00 - 22:00")
            $table->string('opening_hours')->nullable()->comment('Giờ mở cửa');

            // Cờ bật/tắt hoạt động của chi nhánh, mặc định là đang hoạt động
            $table->boolean('is_active')->default(true)->comment('Chi nhánh còn hoạt động hay không');

            // created_at, updated_at do Eloquent tự quản lý
            $table->timestamps();
        });
    }

    /**
     * Quay lại migration: xóa bảng `store`.
     * Lưu ý: phải chạy sau khi các bảng con có khóa ngoại tham chiếu tới đây
     * đã bị xóa (thứ tự down() của các migration khác đảm bảo điều này).
     */
    public function down(): void
    {
        Schema::dropIfExists('store');
    }
};
