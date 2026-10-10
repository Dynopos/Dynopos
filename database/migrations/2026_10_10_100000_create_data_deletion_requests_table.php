<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak permintaan pemadaman data.
 *
 * Meta mewajibkan app memulangkan URL status dan kod pengesahan yang boleh
 * disemak kemudian. PDPA Akta 709 pula memerlukan Data User menunjukkan
 * permintaan subjek data benar-benar dilayan.
 *
 * Satu jadual memenuhi kedua-duanya.
 *
 * `meta_user_id` disimpan sebagai rekod, BUKAN sebagai data peribadi yang
 * berguna: selepas pemadaman ia tidak lagi terpaut kepada apa-apa dalam
 * sistem. Ia perlu supaya permintaan berulang untuk id yang sama boleh
 * dikenal pasti tanpa menyimpan semula apa yang baru dipadam.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->string('confirmation_code')->unique();
            $table->string('meta_user_id')->nullable()->index();

            // Sengaja nullOnDelete, bukan cascade: rekod permintaan mesti
            // kekal walaupun akaun dipadam. Itu buktinya ia dilayan.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // received = diterima; completed = data Facebook dipadam;
            // no_match = tiada data untuk id itu dalam sistem ini.
            $table->string('status')->default('received');

            $table->text('summary')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_deletion_requests');
    }
};
