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
            $body = $this->get("{$this->pageId}/posts", [
                'fields' => implode(',', [
                    'id',
                    'message',
                    'full_picture',
                    'permalink_url',
                    'created_time',
                    'shares',
                    'reactions.summary(true)',
                    'comments.summary(true)',
                ]),
                // Minta lebih daripada yang diperlukan: posting tanpa gambar
                // ditapis keluar selepas ini, jadi tanpa lebihan satu halaman
                // boleh pulang hampir kosong.
                'limit' => min($limit * 2, 100),
                'after' => $after,
            ]);

            $posts = collect(data_get($body, 'data', []))
                ->filter(fn (array $post) => filled(data_get($post, 'full_picture')))
                ->map(fn (array $post) => $this->normalise($post))
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
     * Satu posting. Diperlukan oleh laluan fallback, yang memerlukan gambar
     * dan teks posting untuk disalin menjadi creative baharu.
     */
    public function find(string $postId): ?array
    {
        $body = $this->get($postId, [
            'fields' => 'id,message,full_picture,permalink_url,created_time',
        ]);

        return filled(data_get($body, 'id')) ? $this->normalise($body) : null;
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
    protected function normalise(array $post): array
    {
        $message = (string) data_get($post, 'message', '');

        return [
            'id' => (string) data_get($post, 'id'),
            'message' => $message,
            'excerpt' => str($message)->squish()->limit(110)->value(),
            'full_picture' => data_get($post, 'full_picture'),
            'permalink_url' => data_get($post, 'permalink_url'),
            'created_time' => data_get($post, 'created_time'),
            'reactions' => (int) data_get($post, 'reactions.summary.total_count', 0),
            'comments' => (int) data_get($post, 'comments.summary.total_count', 0),
            'shares' => (int) data_get($post, 'shares.count', 0),
        ];
    }

    /** Satu-satunya laluan HTTP dalam kelas ini, dan ia GET. */
    protected function get(string $path, array $query): array
    {
        $response = $this->request()->get($this->url($path), array_filter(
            $query + ['access_token' => $this->token],
            fn ($value) => $value !== null && $value !== '',
        ));

        return $this->unwrap($response, $path);
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
