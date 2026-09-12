<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Bổ sung thông tin xác thực thiết bị và lease để giao PrintJob an toàn qua API. */
return new class extends Migration
{
    /** Token máy in chỉ lưu hash; claim token ràng buộc callback với đúng lượt nhận job. */
    public function up(): void
    {
        Schema::table('printers', function (Blueprint $table): void {
            $table->char('api_token_hash', 64)->nullable()->unique()->after('is_active');
            $table->string('api_token_hint', 12)->nullable()->after('api_token_hash');
        });

        Schema::table('print_jobs', function (Blueprint $table): void {
            // Payment ID biến việc tạo hóa đơn thành idempotent theo giao dịch thanh toán.
            $table->foreignId('payment_id')->nullable()->after('order_id')->constrained('payments')->nullOnDelete();
            $table->char('claim_token_hash', 64)->nullable()->after('status');
            $table->timestamp('claimed_at')->nullable()->after('claim_token_hash');
            $table->timestamp('lease_expires_at')->nullable()->after('claimed_at');
            $table->unique(['payment_id', 'print_type']);
            $table->index(['printer_id', 'status', 'lease_expires_at']);
            $table->index(['store_id', 'status', 'created_at']);
        });
    }

    /** Gỡ index trước cột để rollback hoạt động trên cả MySQL và SQLite. */
    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table): void {
            $table->dropIndex(['store_id', 'status', 'created_at']);
            $table->dropIndex(['printer_id', 'status', 'lease_expires_at']);
            $table->dropUnique(['payment_id', 'print_type']);
            $table->dropConstrainedForeignId('payment_id');
            $table->dropColumn(['claim_token_hash', 'claimed_at', 'lease_expires_at']);
        });

        Schema::table('printers', function (Blueprint $table): void {
            $table->dropUnique(['api_token_hash']);
            $table->dropColumn(['api_token_hash', 'api_token_hint']);
        });
    }
};
