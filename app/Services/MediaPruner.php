<?php

namespace App\Services;

use App\Models\AdSet;
use App\Models\AutoAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Buang fail media set iklan yang sudah lama selesai.
 *
 * Kenapa ini perlu wujud: video dibenarkan sehingga 100MB setiap fail, empat
 * fail setiap set iklan, dan sebelum kelas ini tiada apa-apa yang memadamnya.
 * Setiap video yang pernah dimuat naik kekal di cakera selamanya. Itu kos yang
 * berkembang tanpa had, DAN ia melanggar Prinsip Penyimpanan PDPA — data
 * peribadi tidak boleh disimpan lebih lama daripada tujuannya memerlukan.
 *
 * Apa yang TIDAK dibuang: baris ad_variants, caption, dan metrics_daily. Itu
 * sejarah prestasi peniaga — kecil, dan satu-satunya sebab dia boleh tahu
 * creative mana yang menang tahun lepas. Yang dibuang hanyalah fail besar.
 *
 * Meta memegang salinannya sendiri melalui image_hash dan video_id, jadi iklan
 * yang sedang berjalan tidak terjejas. Yang hilang hanyalah pratonton dalam
 * app, dan keupayaan melancarkan semula set iklan lama tanpa memuat naik
 * semula.
 */
class MediaPruner
{
    /**
     * Set iklan yang layak dibuang medianya.
     *
     * Dua syarat, kedua-duanya konservatif:
     *
     * 1. TIADA variant yang masih `active`. Iklan yang sedang berjalan tidak
     *    disentuh walaupun ia berjalan bertahun — peniaga masih melihatnya
     *    setiap hari dalam dashboard.
     * 2. Set iklan itu tidak disentuh melebihi tempoh simpanan. `updated_at`
     *    bergerak setiap kali status berubah, jadi set yang baru dipause minggu
     *    lepas selamat.
     *
     * @return Collection<int, AdSet>
     */
    public function candidates(?int $days = null): Collection
    {
        $days = $days ?? (int) config('dynoads.privacy.retention_days', 90);

        return AdSet::query()
            ->withoutGlobalScope('user')
            ->where('updated_at', '<', now()->subDays($days))
            ->whereDoesntHave('variants', fn ($q) => $q->where('status', 'active'))
            ->whereHas('variants', fn ($q) => $q->whereNull('media_pruned_at')
                ->where(fn ($w) => $w->whereNotNull('image_path')->orWhereNotNull('video_path')))
            ->with('variants')
            ->get();
    }

    /**
     * Buang fail media untuk satu set iklan.
     *
     * @return array{fail:int, bait:int}
     */
    public function prune(AdSet $set, bool $dryRun = false): array
    {
        $fail = 0;
        $bait = 0;
        $disk = Storage::disk('public');

        foreach ($set->variants as $variant) {
            foreach ([$variant->image_path, $variant->video_path] as $path) {
                if (blank($path) || ! $this->isOurs($path, $set->id)) {
                    continue;
                }

                if ($disk->exists($path)) {
                    $bait += (int) $disk->size($path);
                    $fail++;

                    if (! $dryRun) {
                        $disk->delete($path);
                    }
                }
            }

            if (! $dryRun) {
                // Laluan dikosongkan supaya skrin tidak cuba memuatkan fail
                // yang sudah tiada; media_pruned_at memberitahu KENAPA ia tiada.
                $variant->forceFill([
                    'image_path' => null,
                    'video_path' => null,
                    'media_pruned_at' => now(),
                ])->save();
            }
        }

        if (! $dryRun && $fail > 0) {
            AutoAction::start(
                action: 'prune_media',
                reason: 'Fail media dibuang selepas tempoh simpanan tamat. '
                    .'Angka prestasi dan caption kekal.',
                set: $set,
                payload: ['fail' => $fail, 'bait' => $bait],
            )->succeed("{$fail} fail dibuang (".$this->humanBytes($bait).').');
        }

        return ['fail' => $fail, 'bait' => $bait];
    }

    /**
     * Fail ini memang milik set iklan ini?
     *
     * Laluan dalam pangkalan data sepatutnya sentiasa "dynoads/{set}/n.ext",
     * tetapi kelas ini memadam fail — jadi ia mengesahkan bentuk itu sendiri
     * dan bukan mempercayai apa yang ada dalam lajur. Satu baris yang rosak
     * tidak sepatutnya boleh menyuruh kita memadam fail di tempat lain.
     */
    protected function isOurs(string $path, int $setId): bool
    {
        return str_starts_with($path, "dynoads/{$setId}/")
            && ! str_contains($path, '..');
    }

    public function humanBytes(int $bytes): string
    {
        return match (true) {
            $bytes >= 1073741824 => round($bytes / 1073741824, 1).' GB',
            $bytes >= 1048576 => round($bytes / 1048576, 1).' MB',
            $bytes >= 1024 => round($bytes / 1024).' KB',
            default => $bytes.' bait',
        };
    }
}
