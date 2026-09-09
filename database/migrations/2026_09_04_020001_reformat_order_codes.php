<?php

/**
 * Chuẩn hóa mã order cũ theo định dạng ORD-MMDDYY-NNN.
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Đánh lại số thứ tự theo ngày tạo và giữ nguyên thứ tự phát sinh của order. */
    public function up(): void
    {
        $sequences = [];

        DB::table('orders')
            ->select(['id', 'created_at'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->each(function (object $order) use (&$sequences): void {
                $dateCode = $order->created_at
                    ? date('mdy', strtotime($order->created_at))
                    : date('mdy');
                $sequences[$dateCode] = ($sequences[$dateCode] ?? 0) + 1;

                DB::table('orders')
                    ->where('id', $order->id)
                    ->update([
                        'code' => 'ORD-'.$dateCode.'-'.str_pad((string) $sequences[$dateCode], 3, '0', STR_PAD_LEFT),
                    ]);
            });
    }

    /** Không khôi phục mã cũ vì mã mới là dữ liệu chính thức đã được lưu. */
    public function down(): void
    {
        // Không thực hiện rollback dữ liệu code đã được chuẩn hóa.
    }
};
