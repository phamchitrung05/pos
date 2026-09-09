<?php

/**
 * Thêm mã hiển thị riêng cho order để không phải dùng trực tiếp khóa số tự tăng.
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Thêm cột nullable trước để có thể backfill các order đã tồn tại. */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('code', 50)->nullable()->after('id');
        });

        // Mã cũ ổn định theo ngày tạo và id, bảo đảm không trùng khi nâng cấp dữ liệu.
        DB::table('orders')
            ->select(['id', 'created_at'])
            ->orderBy('id')
            ->get()
            ->each(function (object $order): void {
                $date = $order->created_at
                    ? date('ymd', strtotime($order->created_at))
                    : date('ymd');

                DB::table('orders')
                    ->where('id', $order->id)
                    ->update(['code' => "ORD-{$date}-".str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]);
            });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('code', 50)->nullable(false)->unique()->change();
        });
    }

    /** Xóa index và cột mã order khi rollback migration. */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
