<?php

use App\Exceptions\MetaApiException;
use App\Models\AdSet;
use App\Models\AdVariant;
use App\Models\AutoAction;
use App\Services\AdLauncher;
use App\Services\MetaAdsService;
use App\Services\PagePostService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Fasa 1 — posting Page sedia ada sebagai creative iklan.
 */
beforeEach(function () {
    Http::preventStrayRequests();
});

// ---------------------------------------------------------------- peraturan 13

it('PagePostService tiada satu pun laluan HTTP yang menulis — peraturan 13', function () {
    $source = file_get_contents(app_path('Services/PagePostService.php'));

    foreach (['Http::post', 'Http::put', 'Http::patch', 'Http::delete', '->post(', '->put(', '->patch(', '->delete('] as $forbidden) {
        expect($source)->not->toContain($forbidden);
    }

    expect($source)->toContain('->get(');
});

it('PagePostService tiada method yang namanya menandakan penulisan — peraturan 13', function () {
    $writeVerbs = ['create', 'update', 'delete', 'publish', 'edit', 'remove', 'destroy', 'store', 'save'];

    foreach (get_class_methods(PagePostService::class) as $method) {
        foreach ($writeVerbs as $verb) {
            expect(str_starts_with(strtolower($method), $verb))->toBeFalse(
                "PagePostService::{$method}() nampak seperti operasi tulis."
            );
        }
    }
});

// ------------------------------------------------------------- object_story_id

it('menghantar object_story_id dengan format {page}_{post}', function () {
    Http::fake(['*/adcreatives' => Http::response(['id' => 'cr_1'])]);

    $id = app(MetaAdsService::class)->createCreativeFromPost('DYNOADS-1-1-21SEP26', '377330642350146_101');

    expect($id)->toBe('cr_1');

    Http::assertSent(function (Request $request) {
        $cta = json_decode($request->data()['call_to_action'] ?? '{}', true);

        return str_contains($request->url(), '/adcreatives')
            && ($request->data()['object_story_id'] ?? null) === '377330642350146_101'
            && ($cta['type'] ?? null) === 'WHATSAPP_MESSAGE';
    });
});

it('tidak menggandakan page id bila post id datang tanpa awalan', function () {
    $meta = app(MetaAdsService::class);

    expect($meta->objectStoryId('101'))->toBe('377330642350146_101')
        ->and($meta->objectStoryId('377330642350146_101'))->toBe('377330642350146_101');
});

it('tidak menghantar object_story_spec bersama object_story_id', function () {
    Http::fake(['*/adcreatives' => Http::response(['id' => 'cr_1'])]);

    app(MetaAdsService::class)->createCreativeFromPost('DYNOADS-1-1-21SEP26', '377330642350146_101');

    Http::assertSent(fn (Request $r) => ! array_key_exists('object_story_spec', $r->data()));
});

// -------------------------------------------------------------------- fallback

/** Bentuk ralat Meta bila butang WhatsApp ditolak di atas posting. */
function ctaRejection(): array
{
    return ['error' => [
        'message' => 'Invalid parameter: call_to_action is not supported for this creative',
        'code' => 100,
        'error_subcode' => 1885183,
    ]];
}

function existingPostVariant(): AdVariant
{
    $set = AdSet::factory()->create();

    return AdVariant::factory()->for($set, 'adSet')->create([
        'source_type' => 'existing_post',
        'source_post_id' => '377330642350146_101',
        'caption' => 'Sistem POS sekali bayar.',
        'meta_image_hash' => 'hash123',
    ]);
}

it('jatuh ke laluan salinan bila Meta tolak butang WhatsApp, dan merekodnya', function () {
    $variant = existingPostVariant();
    $creativeCalls = 0;

    Http::fake([
        '*/campaigns' => Http::response(['id' => 'c1']),
        '*/adsets' => Http::response(['id' => 's1']),
        '*/ads' => Http::response(['id' => 'a1']),
        '*/adcreatives' => function () use (&$creativeCalls) {
            $creativeCalls++;

            // Percubaan pertama ialah laluan object_story_id; Meta menolaknya.
            return $creativeCalls === 1
                ? Http::response(ctaRejection(), 400)
                : Http::response(['id' => 'cr_salinan']);
        },
    ]);

    app(AdLauncher::class)->createAll($variant->adSet->load('variants'));

    expect($variant->refresh()->meta_creative_id)->toBe('cr_salinan')
        ->and($variant->status)->toBe('paused');

    $action = AutoAction::where('action', 'post_creative_fallback')->first();

    expect($action)->not->toBeNull()
        ->and($action->result)->toBe('ok')
        ->and($action->payload['post_id'])->toBe('377330642350146_101')
        ->and($action->payload['kesan'])->toContain('TIDAK akan terkumpul pada posting asal');
});

it('laluan salinan menghantar object_story_spec, bukan object_story_id', function () {
    $variant = existingPostVariant();
    $creativeCalls = 0;

    Http::fake([
        '*/campaigns' => Http::response(['id' => 'c1']),
        '*/adsets' => Http::response(['id' => 's1']),
        '*/ads' => Http::response(['id' => 'a1']),
        '*/adcreatives' => function () use (&$creativeCalls) {
            $creativeCalls++;

            return $creativeCalls === 1
                ? Http::response(ctaRejection(), 400)
                : Http::response(['id' => 'cr_salinan']);
        },
    ]);

    app(AdLauncher::class)->createAll($variant->adSet->load('variants'));

    Http::assertSent(function (Request $r) {
        if (! str_contains($r->url(), '/adcreatives') || ! isset($r->data()['object_story_spec'])) {
            return false;
        }

        $spec = json_decode($r->data()['object_story_spec'], true);

        return ($spec['link_data']['image_hash'] ?? null) === 'hash123';
    });
});

it('tidak jatuh ke laluan salinan untuk ralat yang bukan tentang butang', function () {
    $variant = existingPostVariant();

    Http::fake([
        '*/campaigns' => Http::response(['id' => 'c1']),
        '*/adsets' => Http::response(['id' => 's1']),
        '*/adcreatives' => Http::response([
            'error' => ['message' => 'Error validating access token: Session has expired', 'code' => 190],
        ], 400),
    ]);

    expect(fn () => app(AdLauncher::class)->createAll($variant->adSet->load('variants')))
        ->toThrow(MetaApiException::class);

    expect(AutoAction::where('action', 'post_creative_fallback')->count())->toBe(0)
        ->and($variant->refresh()->status)->toBe('failed');
});

it('variant daripada posting tidak memuat naik gambar ke Meta', function () {
    $variant = existingPostVariant();

    Http::fake([
        '*/campaigns' => Http::response(['id' => 'c1']),
        '*/adsets' => Http::response(['id' => 's1']),
        '*/adcreatives' => Http::response(['id' => 'cr1']),
        '*/ads' => Http::response(['id' => 'a1']),
    ]);

    app(AdLauncher::class)->createAll($variant->adSet->load('variants'));

    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/adimages'));
});
