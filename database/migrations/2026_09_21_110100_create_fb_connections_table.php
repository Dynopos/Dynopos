<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kredential Meta setiap peniaga.
     *
     * Satu baris di sini menggantikan empat baris .env yang selama ini hanya
     * boleh memegang seorang pemilik: token, ad account, Page dan nombor
     * WhatsApp. Selepas ini .env cuma fallback pemilik.
     *
     * `token` disimpan sebagai text kerana nilainya panjang (System User token
     * ~200 aksara) dan encryption Laravel membesarkannya lagi. Ia TIDAK pernah
     * disimpan mentah — lihat cast `encrypted` dalam model FbConnection.
     * Peraturan mutlak #8.
     *
     * Fasa 7b menukar cara baris ini DIISI (Facebook Login for Business
     * menggantikan tampal manual). Bentuk jadual tidak perlu berubah.
     */
    public function up(): void
    {
        Schema::create('fb_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->text('token');
            $table->string('ad_account_id');          // sentiasa berawalan act_
            $table->string('page_id');
            $table->string('page_name')->nullable();
            $table->string('wa_phone');               // 60XXXXXXXXX
            $table->string('meta_user_id')->nullable();

            // Token System User boleh "Never". Null bermakna tiada tarikh luput
            // yang diketahui — bukan bermakna ia sudah luput.
            $table->timestamp('expires_at')->nullable();

            $table->string('status')->default('active');  // active|invalid|revoked
            $table->string('last_error')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            // Seorang user satu sambungan aktif. Fasa 8 boleh longgarkan ini
            // kalau satu akaun perlu banyak Page.
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fb_connections');
    }
};
