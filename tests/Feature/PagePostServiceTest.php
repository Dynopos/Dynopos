<?php

use App\Exceptions\MetaApiException;
use App\Services\PagePostService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    Cache::flush();

    // Page "new Pages experience" mahu token Page untuk /posts. Service
    // menukar token System User kepada token Page dahulu.
    Http::fake(['*/377330642350146?fields=access_token*' => Http::response(['access_token' => 'PAGE_TOKEN', 'id' => '377330642350146'])]);
});

function pagePostsBody(array $data, ?string $after = null): array
{
    return [
        'data' => $data,
        'paging' => $after ? ['cursors' => ['after' => $after]] : [],
    ];
}

it('memparse ringkasan reaksi, komen dan share dengan betul', function () {
    Http::fake(['*/posts*' => Http::response(pagePostsBody([[
        'id' => '377330642350146_101',
        'message' => 'Sistem POS sekali bayar.    Tiada langganan bulanan.',
        'full_picture' => 'https://scontent.example/1.jpg',
        'permalink_url' => 'https://facebook.com/101',
        'created_time' => '2026-09-01T08:00:00+0000',
        'shares' => ['count' => 7],
        'reactions' => ['data' => [], 'summary' => ['total_count' => 132]],
        'comments' => ['data' => [], 'summary' => ['total_count' => 18]],
    ]]))]);

    $post = app(PagePostService::class)->listPosts()['posts'][0];

    expect($post['reactions'])->toBe(132)
        ->and($post['comments'])->toBe(18)
        ->and($post['shares'])->toBe(7)
        ->and($post['id'])->toBe('377330642350146_101')
        ->and($post['excerpt'])->toBe('Sistem POS sekali bayar. Tiada langganan bulanan.');
});

it('mengira sifar bila Meta tinggalkan medan ringkasan', function () {
    Http::fake(['*/posts*' => Http::response(pagePostsBody([[
        'id' => '377330642350146_102',
        'message' => 'Posting tanpa engagement',
        'full_picture' => 'https://scontent.example/2.jpg',
        'created_time' => '2026-09-02T08:00:00+0000',
    ]]))]);

    $post = app(PagePostService::class)->listPosts()['posts'][0];

    expect($post['reactions'])->toBe(0)
        ->and($post['comments'])->toBe(0)
        ->and($post['shares'])->toBe(0);
});

it('menapis keluar posting yang tiada gambar', function () {
    Http::fake(['*/posts*' => Http::response(pagePostsBody([
        ['id' => '1_1', 'message' => 'Status teks sahaja', 'created_time' => '2026-09-01T08:00:00+0000'],
        [
            'id' => '1_2',
            'message' => 'Ada gambar',
            'full_picture' => 'https://scontent.example/2.jpg',
            'created_time' => '2026-09-02T08:00:00+0000',
        ],
    ]))]);

    $posts = app(PagePostService::class)->listPosts()['posts'];

    expect($posts)->toHaveCount(1)
        ->and($posts[0]['id'])->toBe('1_2');
});

it('meminta medan ringkasan reaksi dan komen daripada Graph API', function () {
    Http::fake(['*/posts*' => Http::response(pagePostsBody([]))]);

    app(PagePostService::class)->listPosts();

    Http::assertSent(function (Request $request) {
        $fields = $request->data()['fields'] ?? '';

        return str_contains($request->url(), '/v21.0/377330642350146/posts')
            && str_contains($fields, 'reactions.summary(true)')
            && str_contains($fields, 'comments.summary(true)')
            && str_contains($fields, 'full_picture')
            && str_contains($fields, 'permalink_url');
    });
});

it('memulangkan cursor seterusnya untuk paging', function () {
    Http::fake(['*/posts*' => Http::response(pagePostsBody([], 'CURSOR_XYZ'))]);

    expect(app(PagePostService::class)->listPosts()['next_cursor'])->toBe('CURSOR_XYZ');
});

it('menyimpan dalam cache supaya Graph API dipanggil sekali sahaja', function () {
    Http::fake(['*/posts*' => Http::response(pagePostsBody([[
        'id' => '1_1',
        'message' => 'Sekali sahaja',
        'full_picture' => 'https://scontent.example/1.jpg',
        'created_time' => '2026-09-01T08:00:00+0000',
    ]]))]);

    $service = app(PagePostService::class);
    $service->listPosts();
    $service->listPosts();

    // Satu tukaran token Page + satu senarai posting. Panggilan kedua dari cache.
    Http::assertSentCount(2);
});

it('membaca posting dengan token Page, bukan token System User', function () {
    Http::fake(['*/posts*' => Http::response(pagePostsBody([]))]);

    app(PagePostService::class)->listPosts();

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/377330642350146?')
        && $r->data()['fields'] === 'access_token'
        && $r->data()['access_token'] === 'TEST_TOKEN');

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/posts')
        && $r->data()['access_token'] === 'PAGE_TOKEN');
});

it('jatuh balik ke token asal bila Meta tidak memberi token Page', function () {
    Http::fake([
        '*fields=access_token*' => Http::response(['error' => ['message' => 'no', 'code' => 200]], 400),
        '*/posts*' => Http::response(pagePostsBody([])),
    ]);

    // Page lain: fake token Page dalam beforeEach hanya untuk 377330642350146.
    (new PagePostService(token: 'TEST_TOKEN', pageId: '999'))->listPosts();

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/999/posts')
        && $r->data()['access_token'] === 'TEST_TOKEN');
});

it('token Page tidak disimpan dalam cache — peraturan #8', function () {
    Http::fake(['*/posts*' => Http::response(pagePostsBody([]))]);

    app(PagePostService::class)->listPosts();

    expect(serialize(Cache::get('dynoads.page_posts.377330642350146.'.config('dynoads.page_posts.per_page').'.first')))
        ->not->toContain('PAGE_TOKEN');
});

it('membuang ralat Graph API tanpa mendedahkan token — peraturan #8', function () {
    Http::fake(['*/posts*' => Http::response([
        'error' => ['message' => 'Invalid OAuth access token.', 'code' => 190],
    ], 400)]);

    expect(fn () => app(PagePostService::class)->listPosts())
        ->toThrow(MetaApiException::class);

    try {
        app(PagePostService::class)->listPosts();
    } catch (MetaApiException $e) {
        expect($e->getMessage())->not->toContain('TEST_TOKEN')
            ->and($e->errorCode)->toBe(190);
    }
});
