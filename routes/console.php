<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Buang fail media set iklan yang sudah lama selesai.
 *
 * Video dibenarkan sehingga 100MB setiap fail dan tiada apa yang memadamnya
 * sebelum ini — setiap video kekal di cakera selamanya. Itu kos yang
 * berkembang tanpa had, dan Prinsip Penyimpanan PDPA tidak membenarkan data
 * peribadi disimpan lebih lama daripada tujuannya memerlukan.
 *
 * 3:30 pagi waktu Malaysia: selepas lonjakan tengah malam, sebelum peniaga
 * bangun. withoutOverlapping supaya pembersihan yang perlahan tidak bertindih
 * dengan pusingan esok.
 */
Schedule::command('dynoads:prune-media')
    ->dailyAt('03:30')
    ->timezone('Asia/Kuala_Lumpur')
    ->withoutOverlapping();
