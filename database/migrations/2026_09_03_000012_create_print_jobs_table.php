<?php

/**
 * Migration: Tạo bảng `print_jobs` (Lệnh in / Lịch sử in).
 *
 * Lưu các lệnh in gửi tới máy in: hóa đơn cho khách, phiếu bếp...
 * Các lệnh in thất bại được giữ lại kèm thông báo lỗi để in lại sau.
 *
 * Quan hệ:
 * - print_jobs.printer_id -> printers.id (in trên máy in nào)
 * - print_jobs.order_id   -> orders.id   (in nội dung của đơn hàng nào)
 *
 * Điểm thiết kế quan trọng: cột `payload` lưu snapshot (bản chụp) toàn bộ
 * nội dung cần in dưới dạng JSON, để đề phòng dữ liệu gốc (sản phẩm, giá...)
 * thay đổi sau này thì lệnh in lại vẫn in đúng nội dung ban đầu.
 *
 * Schema nguồn: file JSON thiết kế CSDL (database diagram).
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chạy migration: tạo bảng `print_jobs`.
     */
    public function up(): void
    {
        Schema::create('print_jobs', function (Blueprint $table) {
            // Khóa chính, tự tăng (BIGINT UNSIGNED AUTO_INCREMENT)
            $table->id();

            // Máy in sẽ thực hiện lệnh in này
            // cascadeOnDelete: xóa máy in thì xóa luôn các lệnh in của máy đó
            $table->foreignId('printer_id')
                ->comment('Máy in thực hiện lệnh in')
                ->constrained('printers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Đơn hàng liên quan - cho phép NULL vì có lệnh in không gắn với đơn
            // (VD: in báo cáo ca, in cấu hình...)
            // cascadeOnDelete: xóa đơn thì xóa luôn các lệnh in của đơn đó
            $table->foreignId('order_id')
                ->nullable()
                ->comment('Đơn hàng liên quan đến lệnh in')
                ->constrained('orders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Loại nội dung in: kitchen (phiếu bếp), receipt (hóa đơn)...
            $table->string('print_type', 50)->comment('Loại nội dung in: kitchen, receipt, ...');

            // Trạng thái lệnh in: pending (chờ in), printing (đang in),
            // success (thành công), failed (thất bại), canceled (đã hủy)
            // Mặc định "pending" khi tạo lệnh in mới
            $table->string('status', 50)->default('pending')
                ->comment('pending / printing / success / failed / canceled');

            // Số lần đã thử in (dùng cho cơ chế retry tự động), mặc định 0
            $table->integer('attempts')->default(0)->comment('Số lần đã thử in');

            // Thông báo lỗi khi in thất bại - để chẩn đoán và xử lý in lại
            $table->text('error_message')->nullable()
                ->comment('Lỗi khi in thất bại để xử lý in lại');

            // Bản chụp nội dung cần in dạng JSON - dữ liệu in độc lập với DB gốc
            $table->json('payload')->nullable()
                ->comment('Snapshot nội dung cần in (đề phòng dữ liệu gốc thay đổi)');

            // Thời điểm in thành công (NULL = chưa in xong)
            $table->timestamp('printed_at')->nullable()->comment('Thời điểm in thành công');

            // created_at, updated_at do Eloquent tự quản lý
            $table->timestamps();
        });
    }

    /**
     * Quay lại migration: xóa bảng `print_jobs`.
     * Bảng này không được bảng nào tham chiếu nên có thể xóa độc lập.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};
