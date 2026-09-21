<?php

namespace App\Services;

use App\Exceptions\MetaApiException;
use App\Services\Meta\MetaCredentials;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Semua panggilan Meta Marketing API lalu di sini.
 *
 * SENGAJA TIADA method update*: peraturan mutlak #1 — budget, targeting dan
 * creative campaign sedia ada tidak pernah diedit. Nak ubah = buat campaign baru.
 *
 * activate()/pause() hanya menghantar medan `status`. Ia bukan pengeditan
 * tetapan; ia satu-satunya cara butang RUN boleh berfungsi.
 */
class MetaAdsService
{
    /**
     * Keempat-empat nilai boleh datang dari luar sejak Fasa 7a.
     *
     * Sebelum ini `pageId` dan `waPhone` dibaca terus dari config di dalam
     * method — yang bermakna walaupun token peniaga lain dihantar ke sini,
     * iklan tetap dibuat di atas Page pemilik dan lead pergi ke WhatsApp
     * pemilik. Kedua-duanya kini keadaan objek, bukan pembolehubah global.
     *
     * Fallback config dikekalkan supaya konteks pemilik (arahan artisan,
     * test) terus berfungsi tanpa FbConnection.
     */
    public function __construct(
        protected ?string $token = null,
        protected ?string $adAccountId = null,
        protected ?string $pageId = null,
        protected ?string $waPhone = null,
    ) {
        $this->token ??= (string) config('dynoads.meta.token');
        $this->adAccountId ??= $this->normaliseAccountId((string) config('dynoads.meta.ad_account_id'));
        $this->pageId ??= (string) config('dynoads.meta.page_id');
        $this->waPhone ??= (string) config('dynoads.meta.wa_phone');
    }

    public static function fromCredentials(MetaCredentials $credentials): self
    {
        return new self(
            token: $credentials->token,
            adAccountId: $credentials->adAccountId,
            pageId: $credentials->pageId,
            waPhone: $credentials->waPhone,
        );
    }

    public function pageId(): string
    {
        return $this->pageId;
    }

    // ------------------------------------------------------------------ gambar

    /** Muat naik gambar, pulangkan image_hash. */
    public function uploadImage(string $absolutePath): string
    {
        $filename = basename($absolutePath);

        $response = $this->request()
            ->attach('source', file_get_contents($absolutePath), $filename)
            ->post($this->url("{$this->adAccountId}/adimages"), [
                'access_token' => $this->token,
            ]);

        $data = $this->unwrap($response, "{$this->adAccountId}/adimages");

        $hash = data_get($data, "images.{$filename}.hash")
            ?? data_get($data, 'images.bytes.hash')
            ?? collect(data_get($data, 'images', []))->pluck('hash')->first();

        if (! $hash) {
            throw new MetaApiException('Meta tidak pulangkan image_hash untuk gambar yang dimuat naik.');
        }

        return $hash;
    }

    // ------------------------------------------------------------------ video

    /**
     * Muat naik video, pulangkan video_id.
     *
     * BENTUK PAYLOAD BELUM DISAHKAN TERHADAP DOKUMENTASI RASMI META.
     * developers.facebook.com tidak boleh dicapai dari persekitaran
     * pembangunan ini, jadi bentuk di bawah datang dari sumber sekunder.
     * Peraturan mutlak #3 mengehadkan kerosakannya: semua dibuat PAUSED, jadi
     * bentuk yang salah gagal dengan ralat yang kelihatan semasa Approve —
     * sebelum satu sen dibelanjakan. Sahkan dengan satu video sebenar sebelum
     * mempercayainya.
     */
    public function uploadVideo(string $absolutePath): string
    {
        $filename = basename($absolutePath);

        $response = $this->request()
            ->timeout((int) config('dynoads.video.upload_timeout', 300))
            ->attach('source', file_get_contents($absolutePath), $filename)
            ->post($this->url("{$this->adAccountId}/advideos"), [
                'name' => $filename,
                'access_token' => $this->token,
            ]);

        $id = data_get($this->unwrap($response, "{$this->adAccountId}/advideos"), 'id');

        if (! $id) {
            throw new MetaApiException('Meta tidak pulangkan video_id untuk video yang dimuat naik.');
        }

        return (string) $id;
    }

    /**
     * Tunggu Meta siap memproses video.
     *
     * Ini bukan kemewahan. Pemprosesan video TIDAK SEGERAK: /advideos
     * memulangkan id serta-merta, tetapi creative yang dibuat sebelum
     * pemprosesan selesai ditolak. Tanpa menunggu, iklan video akan gagal
     * secara rawak bergantung pada saiz fail dan beban Meta — kegagalan yang
     * kelihatan seperti pepijat yang tidak boleh diulang.
     *
     * @return array{status:string, thumbnail:?string}
     */
    public function waitForVideo(string $videoId, ?int $timeoutSeconds = null, ?int $pollSeconds = null): array
    {
        $timeout = $timeoutSeconds ?? (int) config('dynoads.video.ready_timeout_seconds', 180);
        $poll = max(1, $pollSeconds ?? (int) config('dynoads.video.poll_seconds', 5));
        $deadline = time() + $timeout;

        do {
            $body = $this->unwrap(
                $this->request()->get($this->url($videoId), [
                    'fields' => 'status,picture',
                    'access_token' => $this->token,
                ]),
                $videoId
            );

            $status = (string) (data_get($body, 'status.video_status') ?: data_get($body, 'status') ?: 'processing');

            if ($status === 'ready') {
                return ['status' => $status, 'thumbnail' => data_get($body, 'picture')];
            }

            if ($status === 'error') {
                throw new MetaApiException('Meta gagal memproses video ini. Cuba video lain atau saiz yang lebih kecil.');
            }

            if (time() >= $deadline) {
                break;
            }

            sleep($poll);
        } while (true);

        throw new MetaApiException(
            "Video masih diproses oleh Meta selepas {$timeout} saat. Cuba lagi sekejap — video itu tidak hilang."
        );
    }

    /**
     * Creative video dengan butang WhatsApp.
     *
     * Gunakan video_data, BUKAN link_data. Meta menolak video_id di dalam
     * link_data (error_subcode 1443050) dengan mesej yang tidak menyebut
     * puncanya langsung.
     *
     * Thumbnail datang dari Meta sendiri (medan `picture` pada video) kerana
     * pelayan ini tiada ffmpeg untuk mengeluarkan frame.
     */
    public function createVideoCreative(string $name, string $videoId, string $message, ?string $thumbnailUrl = null): string
    {
        $videoData = array_filter([
            'video_id' => $videoId,
            'message' => $message,
            'image_url' => $thumbnailUrl,
            'call_to_action' => [
                'type' => config('dynoads.ad.call_to_action_type'),
                'value' => [
                    'link' => $this->whatsappLink(),
                    'app_destination' => 'WHATSAPP',
                ],
            ],
        ]);

        $payload = [
            'name' => $name,
            'object_story_spec' => json_encode([
                'page_id' => $this->pageId,
                'video_data' => $videoData,
            ]),
        ];

        return $this->createObject("{$this->adAccountId}/adcreatives", $payload);
    }

    // --------------------------------------------------------------- campaign

    /** Campaign CBO — bajet di peringkat campaign, sentiasa PAUSED. */
    public function createCampaign(string $name, int $dailyBudgetSen): string
    {
        $payload = [
            'name' => $name,
            'objective' => config('dynoads.campaign.objective'),
            'status' => 'PAUSED',
            'buying_type' => config('dynoads.campaign.buying_type'),
            'bid_strategy' => config('dynoads.campaign.bid_strategy'),
            'daily_budget' => $dailyBudgetSen,
            'special_ad_categories' => json_encode(config('dynoads.campaign.special_ad_categories')),
        ];

        return $this->createObject("{$this->adAccountId}/campaigns", $payload);
    }

    /**
     * Ad set CONVERSATIONS → WhatsApp, sentiasa PAUSED.
     *
     * $waPhone ialah nombor yang peniaga taip untuk set iklan INI. Sebelum
     * Fasa 7a parameter ini tidak wujud dan nombor sentiasa diambil dari
     * config, jadi nombor yang peniaga taip dalam borang diabaikan senyap dan
     * setiap lead pergi ke WhatsApp pemilik app. Ia mesti dihantar.
     *
     * Meta menolak nombor yang tidak disambungkan kepada Page dalam
     * promoted_object. Itu betul: lebih baik iklan gagal dengan ralat yang
     * kelihatan daripada berjaya lalu menghantar lead ke telefon yang salah.
     *
     * @param  array<int, string>  $regionKeys
     */
    public function createAdSet(string $campaignId, string $name, array $regionKeys = [], ?string $waPhone = null): string
    {
        $payload = [
            'name' => $name,
            'campaign_id' => $campaignId,
            'status' => 'PAUSED',
            'billing_event' => config('dynoads.adset.billing_event'),
            'optimization_goal' => config('dynoads.adset.optimization_goal'),
            'destination_type' => config('dynoads.adset.destination_type'),
            'promoted_object' => json_encode([
                'page_id' => $this->pageId,
                'whatsapp_phone_number' => $waPhone ?: $this->waPhone,
            ]),
            'targeting' => json_encode($this->buildTargeting($regionKeys)),
        ];

        return $this->createObject("{$this->adAccountId}/adsets", $payload);
    }

    /** Creative link ad dengan butang WhatsApp. */
    public function createCreative(string $name, string $imageHash, string $message): string
    {
        $payload = [
            'name' => $name,
            'object_story_spec' => json_encode([
                'page_id' => $this->pageId,
                'link_data' => [
                    'link' => $this->whatsappLink(),
                    'message' => $message,
                    'image_hash' => $imageHash,
                    'call_to_action' => [
                        'type' => config('dynoads.ad.call_to_action_type'),
                        'value' => ['app_destination' => 'WHATSAPP'],
                    ],
                ],
            ]),
        ];

        return $this->createObject("{$this->adAccountId}/adcreatives", $payload);
    }

    /**
     * Creative daripada posting Page sedia ada (Fasa 1).
     *
     * Guna object_story_id, bukan object_story_spec. Bezanya penting kepada
     * peniaga: dengan object_story_id, semua like, komen dan share terkumpul
     * pada posting asal dia. Dia nampak posting sendiri naik, bukan empat
     * iklan asing yang tiada kaitan antara satu sama lain.
     *
     * Butang WhatsApp di atas posting sedia ada tidak diterima oleh setiap
     * gabungan objektif dan format, dan Meta tidak mendokumenkan dengan jelas
     * yang mana. Bila ia ditolak, AdLauncher menangkapnya melalui
     * MetaApiException::isCallToActionError() dan jatuh ke laluan salinan.
     */
    public function createCreativeFromPost(string $name, string $postId): string
    {
        $payload = [
            'name' => $name,
            'object_story_id' => $this->objectStoryId($postId),
            'call_to_action' => json_encode([
                'type' => config('dynoads.ad.call_to_action_type'),
                'value' => [
                    'link' => $this->whatsappLink(),
                    'app_destination' => 'WHATSAPP',
                ],
            ]),
        ];

        return $this->createObject("{$this->adAccountId}/adcreatives", $payload);
    }

    /**
     * Bentuk "{page_id}_{post_id}" yang Meta jangka.
     *
     * Graph API memulangkan id posting yang SUDAH berawalan page id, jadi
     * mencantum secara membuta akan menghasilkan "{page}_{page}_{post}" —
     * yang ditolak Meta dengan mesej yang tidak menyebut puncanya langsung.
     * Method ini menerima kedua-dua bentuk dan sentiasa memulangkan satu.
     */
    public function objectStoryId(string $postId): string
    {
        $pageId = $this->pageId;
        $bare = str_contains($postId, '_')
            ? substr($postId, strrpos($postId, '_') + 1)
            : $postId;

        return $pageId.'_'.$bare;
    }

    /**
     * Link WhatsApp berserta ayat pra-isi dalam Bahasa Melayu.
     *
     * Tanpa parameter `text`, Meta mengisi ayat defaultnya sendiri dalam Bahasa
     * Inggeris. Pelanggan hantar Inggeris, AI agent cermin Inggeris — walaupun
     * iklan dan pelanggan dua-dua orang Malaysia.
     */
    public function whatsappLink(): string
    {
        $link = (string) config('dynoads.ad.link');
        $prefill = trim((string) config('dynoads.ad.whatsapp_prefill'));

        if ($prefill === '') {
            return $link;
        }

        return $link.(str_contains($link, '?') ? '&' : '?').http_build_query(['text' => $prefill]);
    }

    /** Ad, sentiasa PAUSED. */
    public function createAd(string $adsetId, string $name, string $creativeId): string
    {
        $payload = [
            'name' => $name,
            'adset_id' => $adsetId,
            'status' => 'PAUSED',
            'creative' => json_encode(['creative_id' => $creativeId]),
        ];

        return $this->createObject("{$this->adAccountId}/ads", $payload);
    }

    // ----------------------------------------------------------------- status

    /**
     * Hidupkan satu objek (campaign/adset/ad). Hanya medan `status` dihantar —
     * tiada budget, targeting atau creative disentuh.
     */
    public function activate(string $objectId): void
    {
        $this->setStatus($objectId, 'ACTIVE');
    }

    /** Pausekan satu objek. Hanya medan `status` dihantar. */
    public function pause(string $objectId): void
    {
        $this->setStatus($objectId, 'PAUSED');
    }

    protected function setStatus(string $objectId, string $status): void
    {
        $response = $this->request()->asForm()->post($this->url($objectId), [
            'status' => $status,
            'access_token' => $this->token,
        ]);

        $this->unwrap($response, $objectId);
    }

    // --------------------------------------------------------------- insights

    /**
     * Insights satu campaign. Pulangkan array rata siap parse.
     *
     * @return array{spend_sen:int,impressions:int,clicks:int,ctr:float,leads:int}
     */
    public function getInsights(string $campaignId, string $datePreset = 'maximum'): array
    {
        $response = $this->request()->get($this->url("{$campaignId}/insights"), [
            'fields' => 'spend,impressions,clicks,ctr,actions',
            'date_preset' => $datePreset,
            'access_token' => $this->token,
        ]);

        $row = data_get($this->unwrap($response, "{$campaignId}/insights"), 'data.0', []);

        return [
            'spend_sen' => (int) round(((float) data_get($row, 'spend', 0)) * 100),
            'impressions' => (int) data_get($row, 'impressions', 0),
            'clicks' => (int) data_get($row, 'clicks', 0),
            'ctr' => (float) data_get($row, 'ctr', 0),
            'leads' => $this->countLeads((array) data_get($row, 'actions', [])),
        ];
    }

    /** Lead WhatsApp = onsite_conversion.total_messaging_connection. */
    public function countLeads(array $actions): int
    {
        $wanted = (array) config('dynoads.metrics.lead_action_types');

        return (int) collect($actions)
            ->filter(fn ($a) => in_array(data_get($a, 'action_type'), $wanted, true))
            ->sum(fn ($a) => (int) data_get($a, 'value', 0));
    }

    // ----------------------------------------------------------- pengesahan

    /**
     * Sahkan kredential ini betul-betul berfungsi, sebelum ia disimpan.
     *
     * Dua GET sahaja — tiada apa-apa dibuat atau diubah. Ia menjawab soalan
     * yang ralat Meta sendiri tidak pernah jawab dengan jelas: adakah token ni
     * sah, dan adakah ia betul-betul boleh capai ad account DAN Page yang
     * peniaga masukkan?
     *
     * Tanpa pemeriksaan ini, kredential salah hanya kelihatan nanti — di
     * tengah-tengah pelancaran, selepas peniaga menaip semuanya dan menekan
     * Approve.
     *
     * @return array{ad_account:string, page:string}
     */
    public function verifyAccess(): array
    {
        $account = $this->unwrap(
            $this->request()->get($this->url($this->adAccountId), [
                'fields' => 'name,account_status',
                'access_token' => $this->token,
            ]),
            $this->adAccountId
        );

        $page = $this->unwrap(
            $this->request()->get($this->url($this->pageId), [
                'fields' => 'name',
                'access_token' => $this->token,
            ]),
            $this->pageId
        );

        return [
            'ad_account' => (string) (data_get($account, 'name') ?: $this->adAccountId),
            'page' => (string) (data_get($page, 'name') ?: $this->pageId),
        ];
    }

    // --------------------------------------------------------------- kawasan

    /**
     * Senarai negeri Malaysia terus dari Meta — kunci region tidak pernah ditekan
     * sendiri dalam kod supaya ia sentiasa sepadan dengan akaun sebenar.
     *
     * @return array<int, array{key:string,name:string}>
     */
    public function searchRegions(string $countryCode = 'MY'): array
    {
        return Cache::remember("dynoads.regions.{$countryCode}", now()->addDay(), function () use ($countryCode) {
            $response = $this->request()->get($this->url('search'), [
                'type' => 'adgeolocation',
                'location_types' => json_encode(['region']),
                'country_code' => $countryCode,
                'limit' => 100,
                'access_token' => $this->token,
            ]);

            return collect(data_get($this->unwrap($response, 'search'), 'data', []))
                ->map(fn ($r) => ['key' => (string) data_get($r, 'key'), 'name' => (string) data_get($r, 'name')])
                ->filter(fn ($r) => $r['key'] !== '' && $r['name'] !== '')
                ->sortBy('name')
                ->values()
                ->all();
        });
    }

    // -------------------------------------------------------------- targeting

    /**
     * Targeting terbukti.
     *
     * $regionKeys kosong = seluruh Malaysia tolak Sarawak / Labuan / Sabah.
     * Kalau ada, Meta disasarkan ke negeri-negeri itu sahaja — ia menerima
     * senarai, jadi satu negeri atau lima sama sahaja.
     *
     * @param  array<int, string>  $regionKeys
     */
    public function buildTargeting(array $regionKeys = []): array
    {
        $t = config('dynoads.targeting');
        $regionKeys = array_values(array_unique(array_filter($regionKeys)));

        $geo = $regionKeys !== []
            ? ['regions' => array_map(fn (string $key) => ['key' => $key], $regionKeys)]
            : [
                'countries' => [$t['country']],
                'excluded_geo_locations' => [
                    'regions' => collect($t['excluded_regions'])
                        ->map(fn ($name, $key) => ['key' => (string) $key])
                        ->values()
                        ->all(),
                ],
            ];

        $geo['location_types'] = $t['location_types'];

        $targeting = [
            'geo_locations' => $geo,
            'age_min' => $t['age_min'],
            'age_max' => $t['age_max'],
            'locales' => $t['locales'],
            'targeting_automation' => ['advantage_audience' => $t['advantage_audience']],
            'brand_safety_content_filter_levels' => $t['brand_safety_content_filter_levels'],
        ];

        // Bila negeri tertentu dipilih, setup manual yang terbukti menghantar field
        // ini. Kalau Meta tolak, set DYNOADS_GEO_TARGETING_AUTOMATION=false.
        if ($regionKeys !== [] && $t['send_geo_targeting_automation']) {
            $targeting['targeting_automation']['individual_setting'] = ['geo_locations' => 1];
        }

        return $targeting;
    }

    // ------------------------------------------------------------------ nama

    /** DYNOADS-{setID}-{n}-{DDMMMYY} */
    public function buildName(int $setId, int $position): string
    {
        return sprintf(
            '%s-%d-%d-%s',
            config('dynoads.name_prefix'),
            $setId,
            $position,
            strtoupper(now()->format('dMy'))
        );
    }

    // ----------------------------------------------------------------- dalaman

    protected function createObject(string $path, array $payload): string
    {
        $response = $this->request()->asForm()->post($this->url($path), $payload + [
            'access_token' => $this->token,
        ]);

        $id = data_get($this->unwrap($response, $path), 'id');

        if (! $id) {
            throw new MetaApiException("Meta tidak pulangkan id untuk {$path}.", endpoint: $path);
        }

        return (string) $id;
    }

    protected function request(): PendingRequest
    {
        return Http::timeout((int) config('dynoads.meta.timeout'))
            ->acceptJson();
    }

    protected function url(string $path): string
    {
        return sprintf(
            '%s/%s/%s',
            rtrim((string) config('dynoads.meta.graph_url'), '/'),
            config('dynoads.meta.api_version'),
            ltrim($path, '/')
        );
    }

    /** Semak ralat Graph API dan pulangkan body. Token tidak pernah masuk mesej. */
    protected function unwrap(Response $response, string $endpoint): array
    {
        $body = (array) $response->json();
        $error = data_get($body, 'error');

        if ($response->failed() || $error) {
            throw new MetaApiException(
                message: (string) (data_get($error, 'message') ?: "Graph API gagal ({$response->status()}) pada {$endpoint}."),
                errorCode: ($c = data_get($error, 'code')) !== null ? (int) $c : null,
                errorSubcode: ($s = data_get($error, 'error_subcode')) !== null ? (int) $s : null,
                userMessage: data_get($error, 'error_user_msg'),
                endpoint: $endpoint,
            );
        }

        return $body;
    }

    protected function normaliseAccountId(string $id): string
    {
        return str_starts_with($id, 'act_') ? $id : 'act_'.$id;
    }
}
