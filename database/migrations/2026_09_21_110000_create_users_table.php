<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jadual user pertama dalam app ini.
     *
     * Sehingga Fasa 7a, Dyno Ads hanya boleh dipakai oleh pemiliknya sendiri:
     * token, ad account dan Page semuanya dari .env. Jadual ini permulaan
     * kepada app yang boleh dijual.
     *
     * `phone` disimpan dalam format 60XXXXXXXXX (tanpa + dan tanpa 0 di depan)
     * supaya ia terus sepadan dengan nombor WhatsApp yang Meta jangka.
     * `phone_verified_at` disediakan sekarang supaya OtpService kemudian tidak
     * memerlukan migration lain.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable()->unique();
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_owner')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
