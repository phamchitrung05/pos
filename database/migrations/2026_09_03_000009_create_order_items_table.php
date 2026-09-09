<?php

/**
 * Migration: Tạo bảng `order_items` (Chi tiết đơn hàng).
 *
 * Lưu từng dòng món trong một đơn hàng: món nào, số lượng bao nhiêu,
 * đơn giá tại thời điểm order, và ghi chú riêng cho món (VD: "ít đá").
 *
 * Quan hệ:
 * - order_items.order_id   -> orders.id (dòng món thuộc đơn hàng nào)
 * - order_items.product_id -> product.id (món hàng được gọi)
 *
 * Lưu ý: `unit_price` được chụp (snapshot) tại thời điểm gọi món để
 * giữ nguyên giá trị đơn ngay cả khi giá sản phẩm thay đổi sau này.
 *
 * Schema nguồn: file JSON thiết kế CSDL (database diagram).
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chạy migration: tạo bảng `order_items`.
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            // Khóa chính, tự tăng (BIGINT UNSIGNED AUTO_INCREMENT)
            $table->id();

            // Đơn hàng chứa dòng món này
            // cascadeOnDelete: xóa đơn thì xóa luôn các dòng món trong đơn
            $table->foreignId('order_id')
                ->comment('Đơn hàng chứa dòng món này')
                ->constrained('orders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Sản phẩm được gọi
            // cascadeOnDelete: xóa sản phẩm thì xóa luôn các dòng món liên quan
            $table->foreignId('product_id')
                ->comment('Sản phẩm được gọi')
                ->constrained('product')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Số lượng món, mặc định 1
            $table->integer('quantity')->default(1)->comment('Số lượng món');

            // Đơn giá tại thời điểm gọi món (snapshot giá của product.price)
            // DECIMAL(10,2) để tính tiền chính xác
            $table->decimal('unit_price', 10, 2)->comment('Đơn giá tại thời điểm gọi món');

            // Ghi chú riêng cho món (VD: "ít đá", "không đường"), cho phép NULL
            $table->text('notes')->nullable()->comment('Ghi chú riêng cho món (VD: ít đá, không đường)');

            // created_at, updated_at do Eloquent tự quản lý
            $table->timestamps();
        });
    }

    /**
     * Quay lại migration: xóa bảng `order_items`.
     * Bảng này không được bảng nào tham chiếu nên có thể xóa độc lập.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
