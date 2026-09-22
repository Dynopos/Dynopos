<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gambar atau video — peniaga pilih sendiri.
 *
 * Meta sendiri mengesyorkan gambar DAN video dalam set yang sama, dan
 * kebanyakan peniaga sudah ada video sendiri di telefon. Sebelum ini app hanya
 * menerima gambar, jadi video mereka tidak boleh dipakai langsung.
 *
 * `image_path` dikekalkan untuk gambar; video mendapat lajurnya sendiri supaya
 * tiada baris lama perlu ditafsir semula. AdVariant::mediaPath() memilih antara
 * keduanya.
 *
 * Laluan video di Meta tidak sama dengan gambar: fail dimuat naik ke
 * /advideos dan diproses secara TIDAK SEGERAK, jadi id video mesti disimpan dan
 * statusnya ditunggu sebelum creative boleh dibuat. Thumbnail datang dari Meta
 * sendiri (medan `picture`) kerana pelayan ini tiada ffmpeg.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_variants', function (Blueprint $table) {
            $table->string('media_type')->default('image')->after('source_type');
            $table->string('video_path')->nullable()->after('image_path');
            $table->string('meta_video_id')->nullable()->after('meta_image_hash');
            $table->text('meta_thumbnail_url')->nullable()->after('meta_video_id');
        });

        // image_path sebelum ini NOT NULL kerana setiap creative ialah gambar.
        // Variant video tiada gambar langsung, jadi lajur itu mesti boleh null.
        Schema::table('ad_variants', function (Blueprint $table) {
            $table->string('image_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ad_variants', function (Blueprint $table) {
            $table->dropColumn(['media_type', 'video_path', 'meta_video_id', 'meta_thumbnail_url']);
        });

        // Baris video mesti pergi dahulu, kalau tidak lajur tidak boleh
        // dikembalikan kepada NOT NULL.
        DB::table('ad_variants')->whereNull('image_path')->delete();

        Schema::table('ad_variants', function (Blueprint $table) {
            $table->string('image_path')->nullable(false)->change();
        });
    }
};
