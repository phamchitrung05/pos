<?php

/**
 * Migration: Tạo bảng `product_group` (Nhóm sản phẩm).
 *
 * Dùng để phân loại thực đơn theo danh mục, ví dụ: "Cà phê", "Trà sữa",
 * "Nước ngọt", "Đồ ăn"... Hiển thị trên màn hình order của nhân viên.
 *
 * Quan hệ:
 * - product_group.store_id -> store.id   (nhóm sản phẩm thuộc chi nhánh nào)
 * - product.product_group_id -> product_group.id (được tham chiếu bởi sản phẩm)
 *
 * Schema nguồn: file JSON thiết kế CSDL (database diagram).
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chạy migration: tạo bảng `product_group`.
     */
    public function up(): void
    {
        Schema::create('product_group', function (Blueprint $table) {
            // Khóa chính, tự tăng (BIGINT UNSIGNED AUTO_INCREMENT)
            $table->id();

            // Chi nhánh sở hữu nhóm sản phẩm (thực đơn có thể khác nhau giữa các chi nhánh)
            // cascadeOnDelete: xóa chi nhánh thì xóa luôn các nhóm của chi nhánh đó
            $table->foreignId('store_id')
                ->comment('Chi nhánh sở hữu nhóm sản phẩm')
                ->constrained('store')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Tên nhóm sản phẩm hiển thị trên màn hình order (VD: "Cà phê")
            $table->string('name')->comment('Tên nhóm sản phẩm');

            // Icon của nhóm - có thể là URL ảnh hoặc định danh icon trong app
            $table->string('icon')->nullable()->comment('URL hoặc định danh icon của nhóm sản phẩm');

            // Thứ tự sắp xếp hiển thị, số nhỏ hơn hiển thị trước, mặc định 0
            $table->integer('sort_order')->default(0)->comment('Thứ tự sắp xếp hiển thị');

            // Cờ bật/tắt hiển thị nhóm, mặc định là bật
            $table->boolean('is_active')->default(true)->comment('Nhóm còn sử dụng hay không');

            // created_at, updated_at do Eloquent tự quản lý
            $table->timestamps();
        });
    }

    /**
     * Quay lại migration: xóa bảng `product_group`.
     * Phải chạy sau khi bảng `product` (bảng tham chiếu tới đây) đã bị xóa.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_group');
    }
};
