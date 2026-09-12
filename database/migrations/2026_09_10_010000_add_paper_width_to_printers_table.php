<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Thêm profile khổ giấy để agent dựng đúng bitmap cho máy 58 mm hoặc 80 mm. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printers', function (Blueprint $table): void {
            $table->unsignedTinyInteger('paper_width_mm')->default(80)->after('port');
        });
    }

    public function down(): void
    {
        Schema::table('printers', function (Blueprint $table): void {
            $table->dropColumn('paper_width_mm');
        });
    }
};
