<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lưu số bản phiếu bếp cần in trên từng máy in. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printers', function (Blueprint $table): void {
            $table->unsignedTinyInteger('kitchen_copies')->default(1)->after('paper_width_mm');
        });
    }

    public function down(): void
    {
        Schema::table('printers', function (Blueprint $table): void {
            $table->dropColumn('kitchen_copies');
        });
    }
};
