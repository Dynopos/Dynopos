<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pemilikan data.
     *
     * Hanya dua jadual mendapat user_id: ad_sets dan poster_jobs. Keduanya akar
     * pemilikan. ad_variants, auto_actions dan metrics_daily mewarisi melalui
     * ad_set — menyalin user_id ke sana akan mencipta dua sumber kebenaran yang
     * boleh bercanggah.
     *
     * user_id SENGAJA nullable. Pangkalan data pengeluaran sudah mengandungi
     * ad_sets sebenar milik pemilik, dan migration ini berjalan sebelum ada
     * seorang user pun. Baris lama menjadi "belum dituntut"; arahan
     * `php artisan dynoads:owner {email}` menciptakan akaun pemilik dan
     * menuntutnya. Sebelum dituntut, baris itu tidak kelihatan kepada sesiapa —
     * tersembunyi, bukan terdedah.
     */
    public function up(): void
    {
        Schema::table('ad_sets', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('poster_jobs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // cache_key ialah sha1 bagi template + data + latar. Dua peniaga yang
        // kebetulan menghasilkan poster serupa akan mengira kunci yang sama.
        // Sebelum ini itu tidak menjadi masalah kerana hanya ada seorang
        // pengguna; sekarang ia dua kali rosak: PosterService::firstOrNew tidak
        // akan nampak baris peniaga lain (global scope menapisnya), lalu cuba
        // menyisip baris baharu dan melanggar unique index.
        Schema::table('poster_jobs', function (Blueprint $table) {
            $table->dropUnique(['cache_key']);
        });

        Schema::table('poster_jobs', function (Blueprint $table) {
            $table->unique(['user_id', 'cache_key']);
        });
    }

    public function down(): void
    {
        Schema::table('poster_jobs', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'cache_key']);
        });

        Schema::table('poster_jobs', function (Blueprint $table) {
            $table->unique('cache_key');
        });

        Schema::table('poster_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('ad_sets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
