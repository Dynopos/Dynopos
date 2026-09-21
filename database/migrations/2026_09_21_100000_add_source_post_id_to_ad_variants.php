<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fasa 1 — posting Page sedia ada sebagai creative.
 *
 * `source_type` sudah wujud sejak enjin poster (upload|poster). Fasa 1 cuma
 * menambah nilai ketiga, `existing_post`, dan satu medan untuk menyimpan
 * posting mana yang dirujuk.
 *
 * ID disimpan sebagaimana Meta memulangkannya, iaitu "{page_id}_{post_id}".
 * Bentuk object_story_id disusun semasa panggilan, bukan disimpan bercantum,
 * supaya menukar Page kemudian tidak merosakkan baris lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_variants', function (Blueprint $table) {
            $table->string('source_post_id')->nullable()->after('poster_job_id');
        });
    }

    public function down(): void
    {
        Schema::table('ad_variants', function (Blueprint $table) {
            $table->dropColumn('source_post_id');
        });
    }
};
