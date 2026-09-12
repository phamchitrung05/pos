<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lưu command từ thiết bị để mọi thao tác POS có idempotency chung. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_commands', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('store_id')->constrained('store')->cascadeOnUpdate()->cascadeOnDelete();
            // Stage đăng ký thiết bị sẽ bổ sung FK; UUID vẫn ổn định từ lúc thiết bị gửi command đầu tiên.
            $table->uuid('device_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 50);
            $table->char('payload_hash', 64);
            $table->string('status', 50)->default('pending');
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status', 'created_at']);
            $table->index(['store_id', 'device_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_commands');
    }
};
