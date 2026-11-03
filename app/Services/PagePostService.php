<?php

namespace App\Services;

use App\Exceptions\MetaApiException;
use App\Services\Meta\MetaCredentials;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Baca posting Page — dan hanya baca.
 *
 * PERATURAN 13: kelas ini tidak mempunyai satu pun method yang menulis,
 * memadam atau mengubah posting. Posting peniaga ialah hasil kerja mereka
 * sendiri, kadangkala bertahun terkumpul dengan like dan komen yang benar.
 * App merujuk post_id untuk dijadikan creative; ia tidak pernah menyentuh
 * posting itu.
 *
 * Kelas ini membina permintaannya sendiri dan bukan mewarisi MetaAdsService,
 * supaya "baca sahaja" boleh dibuktikan dengan membaca satu fail. Ada ujian
 * yang membaca kod sumber fail ini dan gagal kalau sesiapa menambah laluan
 * tulis ke dalamnya.
 */
class PagePostService
{
    /**
     * Token Page, ditukar daripada token System User pada panggilan pertama.
     *
     * Page "new Pages experience" menolak /{page}/posts dengan token System
     * User ("A Page access token is required for this call"). Token Page
     * hanya hidup dalam memori untuk satu permintaan — tidak pernah ke cache,
     * DB atau log (peraturan mutlak #8). false = sudah dicuba, tiada.
     */
    protected string|false|null $pageToken = null;

    public function __construct(
        protected ?string $token = null,
        protected ?string $pageId = null,
    ) {
        $this->token ??= (string) config('dynoads.meta.token');
        $this->pageId ??= (string) config('dynoads.meta.page_id');
    }

    public static function fromCredentials(MetaCredentials $credentials): self
    {
        return new self(token: $credentials->token, pageId: $credentials->pageId);
    }

    /**
     * Posting Page yang bergambar, terbaharu dahulu.
     *
     * @return array{posts: array<int, array<string, mixed>>, next_cursor: ?string}
     */
    public function listPosts(?int $limit = null, ?string $after = null): array
    {
        $limit = max(1, min($limit ?? (int) config('dynoads.page_posts.per_page'), 100));
        $key = sprintf('dynoads.page_posts.%s.%d.%s', $this->pageId, $limit, $after ?: 'first');
        $ttl = now()->addMinutes((int) config('dynoads.page_posts.cache_minutes'));

        return Cache::remember($key, $ttl, function () use ($limit, $after) {
            [$body, $fields] = $this->fetchPosts($limit, $after);

            $posts = collect(data_get($body, 'data', []))
                ->filter(fn (array $post) => filled(data_get($post, 'full_picture')))
                ->map(fn (array $post) => $this->normalise($post, $fields))
                ->take($limit)
                ->values()
                ->all();

            return [
                'posts' => $posts,
                'next_cursor' => data_get($body, 'paging.cursors.after'),
            ];
        });
    }

    /**
     * Medan yang diminta, dari paling lengkap ke paling asas.
     *
     * Kiraan komen memerlukan pages_read_user_content, yang token pelanggan
     * belum tentu ada (dan mungkin perlu App Review). Gambar dan teks posting
     * — yang sebenarnya diperlukan untuk membuat iklan — tidak memerlukannya.
     * Jadi bila Meta menolak atas sebab permission, kita minta lebih sedikit,
     * bukan menggagalkan seluruh skrin.
     */
    protected const FIELD_TIERS = [
        ['posts', ['shares', 'reactions.summary(true)', 'comments.summary(true)']],
        ['posts', ['shares', 'reactions.summary(true)']],
        ['posts', []],
        // Hanya posting yang Page sendiri terbitkan — yang memang kita mahu.
        // Sesetengah Page menolak /posts tanpa pages_read_user_content tetapi
        // membenarkan edge ini.
        ['published_posts', []],
    ];

    protected const BASE_FIELDS = ['id', 'message', 'full_picture', 'permalink_url', 'created_time'];

    /** @return array{0: array, 1: array<int, string>} badan respons dan medan yang berjaya */
    protected function fetchPosts(int $limit, ?string $after): array
    {
        foreach (self::FIELD_TIERS as $i => [$edge, $extra]) {
            $fields = [...self::BASE_FIELDS, ...$extra];

            try {
                $body = $this->get("{$this->pageId}/{$edge}", [
                    'fields' => implode(',', $fields),
                    // Minta lebih daripada yang diperlukan: posting tanpa gambar
                    // ditapis keluar selepas ini, jadi tanpa lebihan satu halaman
                    // boleh pulang hampir kosong.
                    'limit' => min($limit * 2, 100),
                    'after' => $after,
                ]);

                return [$body, $fields];
            } catch (MetaApiException $e) {
                $isLastTier = $i === array_key_last(self::FIELD_TIERS);

                if ($isLastTier || ! $this->isPermissionError($e)) {
                    throw $e;
                }
            }
        }

        throw new \LogicException('FIELD_TIERS tidak boleh kosong.');
    }

    /** (#10) / (#200): token tiada permission untuk medan yang diminta. */
    protected function isPermissionError(MetaApiException $e): bool
    {
        $message = mb_strtolower($e->getMessage());

        return in_array($e->errorCode, [10, 200], true)
            || str_contains($message, 'pages_read_user_content')
            || str_contains($message, 'page public content access');
    }

    /**
     * Satu posting. Diperlukan oleh laluan fallback, yang memerlukan gambar
     * dan teks posting untuk disalin menjadi creative baharu.
     */
    public function find(string $postId): ?array
    {
        $body = $this->get($postId, [
            'fields' => 'id,message,full_picture,permalink_url,created_time',
        ]);

        return filled(data_get($body, 'id')) ? $this->normalise($body, self::BASE_FIELDS) : null;
    }

    public function forget(): void
    {
        Cache::forget(sprintf(
            'dynoads.page_posts.%s.%d.first',
            $this->pageId,
            (int) config('dynoads.page_posts.per_page')
        ));
    }

    /**
     * Ratakan bentuk bersarang Meta kepada sesuatu yang skrin boleh guna terus.
     *
     * Ringkasan reaksi dan komen datang sebagai {data, summary:{total_count}},
     * manakala share datang sebagai {count}. Ketiga-tiganya hilang sepenuhnya
     * apabila nilainya sifar, jadi setiap satu perlukan lalai — bukan null,
     * kerana skrin memaparkannya sebagai nombor.
     */
    protected function normalise(array $post, array $fields): array
    {
        // Medan yang tidak diminta jadi null ("tak diketahui"), bukan 0.
        // Meta tinggalkan medan bila nilainya sifar, jadi 0 hanya bermakna
        // sifar bila medan itu memang diminta.
        $count = fn (string $field, string $path) => in_array($field, $fields, true)
            ? (int) data_get($post, $path, 0)
            : null;

        $message = (string) data_get($post, 'message', '');

        return [
            'id' => (string) data_get($post, 'id'),
            'message' => $message,
            'excerpt' => str($message)->squish()->limit(110)->value(),
            'full_picture' => data_get($post, 'full_picture'),
            'permalink_url' => data_get($post, 'permalink_url'),
            'created_time' => data_get($post, 'created_time'),
            'reactions' => $count('reactions.summary(true)', 'reactions.summary.total_count'),
            'comments' => $count('comments.summary(true)', 'comments.summary.total_count'),
            'shares' => $count('shares', 'shares.count'),
        ];
    }

    /** Satu-satunya laluan HTTP dalam kelas ini, dan ia GET. */
    protected function get(string $path, array $query): array
    {
        return $this->fetch($path, $query, $this->readToken());
    }

    protected function fetch(string $path, array $query, string $token): array
    {
        $response = $this->request()->get($this->url($path), array_filter(
            $query + ['access_token' => $token],
            fn ($value) => $value !== null && $value !== '',
        ));

        return $this->unwrap($response, $path);
    }

    /**
     * Token Page kalau Meta memberinya, kalau tidak token asal.
     *
     * Token asal kekal sebagai fallback kerana ia mungkin sudah pun token
     * Page (sambungan lama), atau Page klasik yang masih menerima token
     * System User. Ralat sebenar, kalau ada, akan datang dari panggilan
     * posting itu sendiri dengan mesej Meta yang jelas.
     */
    protected function readToken(): string
    {
        if ($this->pageToken === null) {
            try {
                $body = $this->fetch((string) $this->pageId, ['fields' => 'access_token'], (string) $this->token);
                $this->pageToken = filled($body['access_token'] ?? null) ? (string) $body['access_token'] : false;
            } catch (MetaApiException) {
                $this->pageToken = false;
            }
        }

        return $this->pageToken ?: (string) $this->token;
    }

    protected function request(): PendingRequest
    {
        return Http::timeout((int) config('dynoads.meta.timeout'))->acceptJson();
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

    /** Peraturan mutlak #8: token tidak pernah masuk ke dalam mesej ralat. */
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
}
