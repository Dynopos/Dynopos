<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Profil bisnes yang diiklankan.
     *
     * Satu peniaga boleh ada lebih dari satu bisnes (pemilik app selalu
     * menyediakan iklan untuk pelanggan). Caption tidak ditulis sehingga
     * analisis bisnes disahkan — lihat BusinessAnalyst.
     */
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description');
            $table->json('analysis')->nullable();
            $table->timestamp('analysis_confirmed_at')->nullable();
            $table->unsignedInteger('analysis_count')->default(0);
            $table->timestamps();
        });

        Schema::table('ad_sets', function (Blueprint $table) {
            $table->foreignId('business_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ad_sets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_id');
        });

        Schema::dropIfExists('businesses');
    }
};
