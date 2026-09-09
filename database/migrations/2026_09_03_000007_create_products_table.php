<?php

/**
 * Migration: Tạo bảng `product` (Sản phẩm / Món).
 *
 * Lưu thông tin từng món trong thực đơn: tên, mô tả, giá bán, trạng thái.
 *
 * Quan hệ:
 * - product.product_group_id -> product_group.id (sản phẩm thuộc nhóm nào)
 * - order_items.product_id -> product.id (được tham chiếu bởi chi tiết đơn hàng)
 *
 * Schema nguồn: file JSON thiết kế CSDL (database diagram).
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chạy migration: tạo bảng `product`.
     */
    public function up(): void
    {
        Schema::create('product', function (Blueprint $table) {
            // Khóa chính, tự tăng (BIGINT UNSIGNED AUTO_INCREMENT)
            $table->id();

            // Nhóm sản phẩm mà món này thuộc về
            // cascadeOnDelete: xóa nhóm thì xóa luôn các sản phẩm thuộc nhóm đó
            $table->foreignId('product_group_id')
                ->comment('Nhóm sản phẩm mà món này thuộc về')
                ->constrained('product_group')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Tên món hiển thị trên thực đơn (VD: "Cà phê sữa đá")
            $table->string('name')->comment('Tên món');

            // Mô tả chi tiết món (nguyên liệu, cách pha chế...), cho phép NULL
            $table->text('description')->nullable()->comment('Mô tả món');

            // Giá bán, DECIMAL(10,2) để tránh lỗi làm tròn của kiểu FLOAT
            // Tối đa 8 chữ số nguyên + 2 chữ số thập phân
            $table->decimal('price', 10, 2)->comment('Giá bán');

            // Cờ bật/tắt bán món này, mặc định là đang bán
            $table->boolean('is_active')->default(true)->comment('Món còn bán hay không');

            // Cờ đánh dấu món có biến thể SKU hay không
            // true = có nhiều biến thể (size, đường đá...), false = bán trực tiếp
            $table->boolean('is_sku')->default(false)->comment('Món có biến thể SKU hay không');

            // created_at, updated_at do Eloquent tự quản lý
            $table->timestamps();
        });
    }

    /**
     * Quay lại migration: xóa bảng `product`.
     * Phải chạy sau khi bảng `order_items` (bảng tham chiếu tới đây) đã bị xóa.
     */
    public function down(): void
    {
        Schema::dropIfExists('product');
    }
};
