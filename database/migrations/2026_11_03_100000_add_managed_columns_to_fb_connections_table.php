<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pelanggan yang diurus oleh pemilik (servis urus iklan).
     *
     * Pelanggan beri Partner Access kepada Business Manager kita, dan Page serta
     * ad account mereka di-assign kepada System User yang token-nya sudah ada
     * dalam .env. Jadi sambungan ini tidak perlu salinan token sendiri:
     * `uses_owner_token` menyuruh MetaCredentials mengambil token .env.
     * Tukar token di .env sekali, semua pelanggan ikut — tiada salinan basi.
     *
     * `managed_by_user_id` menandakan sambungan itu disediakan oleh pemilik,
     * bukan oleh peniaga sendiri. Sambungan seperti itu WAJIB ada persetujuan
     * bertulis (`consented_at`) sebelum sebarang panggilan Meta, kerana duit
     * yang dibelanjakan ialah duit pelanggan, dicaj ke kad mereka sendiri.
     */
    public function up(): void
    {
        Schema::table('fb_connections', function (Blueprint $table) {
            $table->text('token')->nullable()->change();
            $table->boolean('uses_owner_token')->default(false)->after('token');
            $table->foreignId('managed_by_user_id')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('consented_at')->nullable()->after('verified_at');
            $table->string('consent_note', 500)->nullable()->after('consented_at');
        });
    }

    public function down(): void
    {
        Schema::table('fb_connections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('managed_by_user_id');
            $table->dropColumn(['uses_owner_token', 'consented_at', 'consent_note']);
        });
    }
};
