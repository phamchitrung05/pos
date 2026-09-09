<?php

/**
 * Cho phép mỗi Store có bộ đếm mã order riêng theo từng ngày.
 *
 * Ví dụ: Store A và Store B đều có thể có ORD-090426-001. Mã chỉ duy nhất
 * trong phạm vi một Store, vì vậy mọi truy vấn order phải giữ tenant scope.
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tạo sequence, đổi unique code và đánh lại mã cũ theo từng Store/ngày. */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // Bỏ unique toàn hệ thống trước khi cho phép hai Store dùng cùng mã.
            $table->dropUnique(['code']);
        });

        Schema::create('order_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('store')->cascadeOnDelete();
            $table->date('sequence_date');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['store_id', 'sequence_date']);
        });

        // Unique trong cùng Store là đủ; khác Store được phép trùng code.
        Schema::table('orders', function (Blueprint $table): void {
            $table->unique(['store_id', 'code']);
        });

        $sequences = [];

        // Đánh lại order cũ theo thời gian tạo để số thứ tự mỗi ngày bắt đầu từ 001.
        DB::table('orders')
            ->select(['id', 'store_id', 'created_at'])
            ->orderBy('store_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->each(function (object $order) use (&$sequences): void {
                $dateCode = $order->created_at
                    ? date('mdy', strtotime($order->created_at))
                    : date('mdy');
                $sequenceKey = $order->store_id.'-'.$dateCode;
                $sequences[$sequenceKey] = ($sequences[$sequenceKey] ?? 0) + 1;

                DB::table('orders')
                    ->where('id', $order->id)
                    ->update([
                        'code' => 'ORD-'.$dateCode.'-'.str_pad((string) $sequences[$sequenceKey], 3, '0', STR_PAD_LEFT),
                    ]);
            });

        // Đồng bộ bộ đếm để order mới tiếp tục từ sau order cũ, không quay lại 001.
        foreach ($sequences as $sequenceKey => $lastNumber) {
            [$storeId, $dateCode] = explode('-', $sequenceKey, 2);
            $sequenceDate = date('Y-m-d', strtotime('20'.substr($dateCode, 4, 2).'-'.substr($dateCode, 0, 2).'-'.substr($dateCode, 2, 2)));

            DB::table('order_sequences')->insert([
                'store_id' => $storeId,
                'sequence_date' => $sequenceDate,
                'last_number' => $lastNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** Xóa sequence và khôi phục unique code toàn hệ thống khi rollback. */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['store_id', 'code']);
            $table->unique('code');
        });

        Schema::dropIfExists('order_sequences');
    }
};
