<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bila fail media variant ini dibuang.
 *
 * Tanpa lajur ini, variant yang medianya dibuang kelihatan sama seperti variant
 * yang memang tidak pernah ada media — dan peniaga akan tertanya-tanya ke mana
 * perginya gambar dia. Dengan ia, skrin boleh kata "dibuang selepas 90 hari"
 * dan bukan sekadar memaparkan kotak kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_variants', function (Blueprint $table) {
            $table->timestamp('media_pruned_at')->nullable()->after('video_path');
        });
    }

    public function down(): void
    {
        Schema::table('ad_variants', function (Blueprint $table) {
            $table->dropColumn('media_pruned_at');
        });
    }
};
