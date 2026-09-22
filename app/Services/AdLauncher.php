<?php

namespace App\Services;

use App\Exceptions\MetaApiException;
use App\Models\AdSet;
use App\Models\AdVariant;
use App\Models\AutoAction;
use App\Models\MetricDaily;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Orkestra antara DB dan Meta.
 *
 * Setiap tindakan yang mengubah Meta direkod dalam auto_actions DAHULU
 * (peraturan mutlak #4), dan setiap objek dibuat PAUSED (peraturan mutlak #3).
 */
class AdLauncher
{
    public function __construct(protected MetaAdsService $meta) {}

    /**
     * Buat campaign + adset + creative + ad untuk setiap variant. Semua PAUSED.
     * Satu gambar = satu campaign, supaya split test membandingkan gambar.
     */
    public function createAll(AdSet $set): AdSet
    {
        foreach ($set->variants as $variant) {
            if ($variant->meta_ad_id) {
                continue; // sudah dibuat — jangan buat dua kali
            }

            $this->createOne($set, $variant);
        }

        $set->update(['status' => 'created']);

        return $set->refresh();
    }

    public function createOne(AdSet $set, AdVariant $variant): AdVariant
    {
        $name = $this->meta->buildName($set->id, $variant->position);

        $action = AutoAction::start(
            action: 'create_campaign',
            reason: "Buat campaign PAUSED untuk iklan #{$variant->position} ({$name}).",
            set: $set,
            variant: $variant,
            payload: ['name' => $name, 'daily_budget_sen' => $set->daily_budget_sen],
        );

        try {
            $hash = null;

            // Variant daripada posting sedia ada tiada gambar untuk dimuat naik:
            // Meta sudah memegang gambar itu di dalam posting asal.
            if (! $variant->isFromExistingPost()) {
                $hash = $variant->meta_image_hash ?: $this->meta->uploadImage($this->absolutePath($variant->image_path));
                $variant->update(['meta_image_hash' => $hash]);
            }

            $campaignId = $this->meta->createCampaign($name, $set->daily_budget_sen);
            $variant->update(['meta_campaign_id' => $campaignId]);

            // $set->phone ialah nombor WhatsApp yang peniaga taip untuk set ini.
            // Ia disimpan sejak Fasa 0 tetapi tidak pernah dihantar ke Meta,
            // jadi setiap lead sampai ke WhatsApp pemilik app.
            $adsetId = $this->meta->createAdSet($campaignId, $name, $set->regionKeyList(), $set->phone);
            $variant->update(['meta_adset_id' => $adsetId]);

            $creativeId = $variant->isFromExistingPost()
                ? $this->creativeFromPost($variant, $name)
                : $this->meta->createCreative($name, (string) $hash, (string) $variant->caption);

            $variant->update(['meta_creative_id' => $creativeId]);

            $adId = $this->meta->createAd($adsetId, $name, $creativeId);

            $variant->update([
                'meta_ad_id' => $adId,
                'status' => 'paused',
                'last_error' => null,
            ]);

            $action->succeed("Campaign {$campaignId} dibuat, status PAUSED.");
        } catch (MetaApiException $e) {
            $variant->update(['status' => 'failed', 'last_error' => $e->forHuman()]);
            $action->fail($e->forHuman());

            throw $e;
        }

        return $variant->refresh();
    }

    /** Butang RUN — satu-satunya tempat duit mula dibelanjakan. */
    public function runAll(AdSet $set): AdSet
    {
        foreach ($set->variants as $variant) {
            if ($variant->status === 'failed' || ! $variant->meta_ad_id) {
                continue;
            }

            $this->runOne($variant);
        }

        $set->update(['status' => 'running']);

        return $set->refresh();
    }

    public function runOne(AdVariant $variant): AdVariant
    {
        $this->guardOwnership($variant);

        $action = AutoAction::start(
            action: 'run',
            reason: "Peniaga tekan RUN untuk iklan #{$variant->position}.",
            variant: $variant,
            payload: ['campaign_id' => $variant->meta_campaign_id],
        );

        try {
            // Campaign dulu, kemudian adset, kemudian ad — supaya tiada ad hidup
            // di bawah campaign yang masih paused.
            $this->meta->activate($variant->meta_campaign_id);
            $this->meta->activate($variant->meta_adset_id);
            $this->meta->activate($variant->meta_ad_id);

            $variant->update(['status' => 'active', 'last_error' => null]);
            $action->succeed('Campaign, ad set dan ad kini ACTIVE.');
        } catch (MetaApiException $e) {
            $variant->update(['last_error' => $e->forHuman()]);
            $action->fail($e->forHuman());

            throw $e;
        }

        return $variant->refresh();
    }

    public function pauseOne(AdVariant $variant, string $reason = 'Peniaga tekan pause.'): AdVariant
    {
        $this->guardOwnership($variant);

        $action = AutoAction::start(
            action: 'pause',
            reason: $reason,
            variant: $variant,
            payload: ['campaign_id' => $variant->meta_campaign_id],
        );

        try {
            $this->meta->pause($variant->meta_ad_id);
            $this->meta->pause($variant->meta_adset_id);
            $this->meta->pause($variant->meta_campaign_id);

            $variant->update(['status' => 'paused']);
            $action->succeed('Iklan dipause.');
        } catch (MetaApiException $e) {
            $variant->update(['last_error' => $e->forHuman()]);
            $action->fail($e->forHuman());

            throw $e;
        }

        return $variant->refresh();
    }

    /** Tarik insights dan simpan. PAPARAN SAHAJA — tiada keputusan automatik di Fasa 0. */
    public function syncMetrics(AdSet $set): AdSet
    {
        $action = AutoAction::start(
            action: 'sync_metrics',
            reason: 'Refresh angka dari Meta.',
            set: $set,
        );

        $failures = [];

        foreach ($set->variants as $variant) {
            if (! $variant->meta_campaign_id) {
                continue;
            }

            try {
                $insights = $this->meta->getInsights($variant->meta_campaign_id);

                MetricDaily::updateOrCreate(
                    ['ad_variant_id' => $variant->id, 'date' => today()->toDateString()],
                    [
                        'spend_sen' => $insights['spend_sen'],
                        'impressions' => $insights['impressions'],
                        'clicks' => $insights['clicks'],
                        'leads' => $insights['leads'],
                        'ctr' => $insights['ctr'],
                    ]
                );
            } catch (MetaApiException $e) {
                $failures[] = "#{$variant->position}: ".$e->forHuman();
            }
        }

        $failures === []
            ? $action->succeed('Angka dikemas kini.')
            : $action->fail(implode(' | ', $failures));

        return $set->refresh();
    }

    /**
     * Peraturan mutlak #2: app tidak pernah menyentuh campaign yang bukan ia buat.
     */
    /**
     * Creative daripada posting Page, dengan laluan kedua bila Meta menolak.
     *
     * Laluan pertama (object_story_id) yang kita mahukan: like dan komen
     * terkumpul pada posting asal peniaga. Kalau Meta tolak butang WhatsApp
     * di atas creative itu, kita salin posting menjadi iklan berasingan —
     * iklan tetap jalan, cuma bukti sosial tidak lagi terkumpul di satu tempat.
     */
    protected function creativeFromPost(AdVariant $variant, string $name): string
    {
        $postId = (string) $variant->source_post_id;

        try {
            return $this->meta->createCreativeFromPost($name, $postId);
        } catch (MetaApiException $e) {
            if (! $e->isCallToActionError()) {
                throw $e;
            }

            return $this->copyPostIntoCreative($variant, $name, $postId, $e);
        }
    }

    /**
     * Laluan kedua: bina creative biasa daripada salinan posting.
     *
     * Gambar dan teks posting sudah disalin ke dalam variant semasa peniaga
     * memilihnya, jadi di sini tiada apa yang perlu dimuat turun — kita guna
     * laluan createCreative() yang sama seperti gambar upload biasa.
     *
     * Direkod dalam auto_actions kerana kesannya nyata kepada peniaga dan dia
     * berhak tahu tanpa perlu bertanya: like, komen dan share iklan ini tidak
     * akan masuk ke posting asalnya.
     */
    protected function copyPostIntoCreative(AdVariant $variant, string $name, string $postId, MetaApiException $rejection): string
    {
        $action = AutoAction::start(
            action: 'post_creative_fallback',
            reason: "Meta tolak butang WhatsApp di atas posting {$postId}. Gambar dan teks posting digunakan sebagai iklan berasingan.",
            variant: $variant,
            payload: [
                'post_id' => $postId,
                'ralat_meta' => $rejection->forHuman(),
                'kesan' => 'Like, komen dan share iklan ini TIDAK akan terkumpul pada posting asal.',
            ],
        );

        try {
            if (blank($variant->image_path)) {
                throw new RuntimeException("Posting {$postId} tiada salinan gambar untuk dijadikan iklan.");
            }

            $hash = $variant->meta_image_hash
                ?: $this->meta->uploadImage($this->absolutePath($variant->image_path));

            $variant->update(['meta_image_hash' => $hash]);

            $creativeId = $this->meta->createCreative($name, $hash, (string) $variant->caption);

            $action->succeed('Iklan salinan dibuat. Engagement tidak akan masuk ke posting asal.');

            return $creativeId;
        } catch (\Throwable $e) {
            $action->fail($e->getMessage());

            throw $e;
        }
    }

    protected function guardOwnership(AdVariant $variant): void
    {
        if (! $variant->isOwnedByApp()) {
            throw new RuntimeException(
                'Iklan ini tiada campaign yang dibuat oleh app. App tidak menyentuh campaign lain dalam akaun.'
            );
        }
    }

    protected function absolutePath(string $relativePath): string
    {
        return Storage::disk('public')->path($relativePath);
    }
}
