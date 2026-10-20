<?php

namespace App\Console\Commands;

use App\Services\MediaPruner;
use Illuminate\Console\Command;

/**
 * Buang fail media set iklan yang sudah lama selesai.
 *
 * Dijadualkan harian. Boleh juga dijalankan tangan, dan --dry-run menunjukkan
 * apa yang AKAN dibuang tanpa memadam apa-apa — jalankan itu dahulu pada
 * pengeluaran sebelum mempercayainya.
 */
class PruneMediaCommand extends Command
{
    protected $signature = 'dynoads:prune-media
                            {--days= : Tempoh simpanan dalam hari (lalai dari config)}
                            {--dry-run : Papar sahaja, jangan padam apa-apa}';

    protected $description = 'Buang fail gambar dan video set iklan yang sudah lama selesai';

    public function handle(MediaPruner $pruner): int
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) config('dynoads.privacy.retention_days', 90);

        if ($days < 1) {
            $this->error('Tempoh simpanan mesti sekurang-kurangnya 1 hari.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $sets = $pruner->candidates($days);

        if ($sets->isEmpty()) {
            $this->info("Tiada set iklan melebihi {$days} hari yang perlu dibersihkan.");

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')
            ."{$sets->count()} set iklan melebihi {$days} hari:");
        $this->newLine();

        $jumlahFail = 0;
        $jumlahBait = 0;

        foreach ($sets as $set) {
            $hasil = $pruner->prune($set, $dryRun);

            $jumlahFail += $hasil['fail'];
            $jumlahBait += $hasil['bait'];

            if ($hasil['fail'] > 0) {
                $this->line(sprintf(
                    '  #%-5d %-40s %2d fail  %s',
                    $set->id,
                    str($set->name)->limit(38)->value(),
                    $hasil['fail'],
                    $pruner->humanBytes($hasil['bait']),
                ));
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s%d fail, %s.',
            $dryRun ? 'AKAN dibuang: ' : 'Dibuang: ',
            $jumlahFail,
            $pruner->humanBytes($jumlahBait),
        ));

        if ($dryRun) {
            $this->comment('Tiada apa dipadam. Buang --dry-run untuk menjalankannya betul-betul.');
        } else {
            $this->comment('Angka prestasi, caption dan sejarah campaign tidak disentuh.');
        }

        return self::SUCCESS;
    }
}
