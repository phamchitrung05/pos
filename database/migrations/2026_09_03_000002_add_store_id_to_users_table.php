<?php

/**
 * Migration: Thêm cột `store_id` vào bảng `users`.
 *
 * Mỗi người dùng (nhân viên/quản lý) của hệ thống POS thuộc về một chi nhánh
 * cụ thể, nên cần khóa ngoại `store_id` tham chiếu tới bảng `store`.
 *
 * Migration này tách riêng khỏi migration tạo bảng `users` mặc định của
 * Laravel (chạy trước theo timestamp) nên phải ALTER TABLE thay vì tạo bảng mới.
 *
 * Schema nguồn: file JSON thiết kế CSDL (database diagram).
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chạy migration: thêm cột `store_id` + ràng buộc khóa ngoại.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Khóa ngoại tới `store.id`, đặt ngay sau cột `id` cho dễ đọc
            // constrained('store'): Laravel tự tạo cột BIGINT + FK + index trỏ tới bảng `store`
            // cascadeOnUpdate: khi `store.id` thay đổi thì `users.store_id` cập nhật theo
            $table->foreignId('store_id')
                ->nullable()
                ->after('id')
                ->constrained('store')
                ->cascadeOnUpdate();
        });
    }

    /**
     * Quay lại migration: gỡ khóa ngoại rồi xóa cột `store_id`.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // dropConstrainedForeignId: vừa drop FK vừa drop cột trong một lệnh
            $table->dropConstrainedForeignId('store_id');
        });
    }
};
