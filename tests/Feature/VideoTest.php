<?php

use App\Exceptions\MetaApiException;
use App\Livewire\AdSets\Create;
use App\Models\AdSet;
use App\Models\AdVariant;
use App\Services\AdLauncher;
use App\Services\MetaAdsService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Video: peniaga selalunya sudah ada video sendiri di telefon, dan Meta
 * mengesyorkan gambar DAN video dalam set yang sama.
 *
 * Laluan video di Meta berbeza sepenuhnya daripada gambar: /advideos bukan
 * /adimages, pemprosesan tidak segerak, dan object_story_spec.video_data bukan
 * link_data.
 */
beforeEach(function () {
    Storage::fake('public');
    masukSebagaiPemilik();

    Http::fake([
        '*/search*' => Http::response(['data' => [['key' => '3847', 'name' => 'Selangor']]]),
    ]);
});

// ---------------------------------------------------------------- muat naik

it('video yang dipilih menjadi variant media_type=video', function () {
    Livewire::test(Create::class)
        ->set('upload', [UploadedFile::fake()->create('kedai.mp4', 2048)])
        ->set('problem', 'Kedai sunyi waktu petang')
        ->set('offer', 'Set makan dua orang RM25')
        ->set('phone', '60123456789')
        ->call('save')
        ->assertHasNoErrors();

    $variant = AdVariant::firstOrFail();

    expect($variant->media_type)->toBe('video')
        ->and($variant->isVideo())->toBeTrue()
        ->and($variant->video_path)->toEndWith('.mp4')
        ->and($variant->image_path)->toBeNull()
        ->and($variant->mediaPath())->toBe($variant->video_path)
        ->and(Storage::disk('public')->exists($variant->video_path))->toBeTrue();
});

it('gambar dan video boleh bercampur dalam satu set', function () {
    Livewire::test(Create::class)
        ->set('upload', [UploadedFile::fake()->image('satu.jpg')])
        ->set('upload', [UploadedFile::fake()->create('dua.mp4', 1024)])
        ->set('problem', 'Kedai sunyi waktu petang')
        ->set('offer', 'Set makan dua orang RM25')
        ->set('phone', '60123456789')
        ->call('save')
        ->assertHasNoErrors();

    expect(AdVariant::pluck('media_type')->all())->toBe(['image', 'video']);
});

it('video tidak dipotong persegi — video menegak kekal menegak', function () {
    // Memaksa 1:1 bermakna memotong kepala orang dalam video yang peniaga
    // rakam sendiri dengan telefon.
    Livewire::test(Create::class)
        ->set('upload', [UploadedFile::fake()->createWithContent('menegak.mp4', 'bait-video-sebenar')])
        ->set('problem', 'Kedai sunyi waktu petang')
        ->set('offer', 'Set makan dua orang RM25')
        ->set('phone', '60123456789')
        ->call('save');

    $variant = AdVariant::firstOrFail();
    $saiz = Storage::disk('public')->size($variant->video_path);

    // Fail disimpan utuh: tiada crop, tiada transcode, tiada tukar kepada jpg.
    expect($saiz)->toBe(strlen('bait-video-sebenar'))
        ->and($variant->video_path)->toEndWith('.mp4')
        ->and($variant->image_path)->toBeNull();
});

// -------------------------------------------------------------------- Meta

it('video dihantar ke /advideos, bukan /adimages', function () {
    Http::fake([
        '*/advideos' => Http::response(['id' => 'v777']),
        '*/v777*' => Http::response(['status' => ['video_status' => 'ready'], 'picture' => 'https://cdn/thumb.jpg']),
        '*/campaigns' => Http::response(['id' => 'c1']),
        '*/adsets' => Http::response(['id' => 's1']),
        '*/adcreatives' => Http::response(['id' => 'cr1']),
        '*/ads' => Http::response(['id' => 'a1']),
    ]);

    $set = AdSet::factory()->create();
    $variant = AdVariant::factory()->for($set, 'adSet')->create([
        'media_type' => 'video',
        'video_path' => 'dynoads/1/1.mp4',
        'image_path' => null,
        'caption' => 'Set makan dua orang RM25.',
    ]);

    Storage::disk('public')->put('dynoads/1/1.mp4', 'bait-video');

    app(AdLauncher::class)->createOne($set, $variant);

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/advideos'));
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/adimages'));

    expect($variant->refresh()->meta_video_id)->toBe('v777')
        ->and($variant->meta_thumbnail_url)->toBe('https://cdn/thumb.jpg')
        ->and($variant->status)->toBe('paused');
});

it('creative video guna video_data, bukan link_data', function () {
    // Meta menolak video_id di dalam link_data (error_subcode 1443050) dengan
    // mesej yang tidak menyebut puncanya langsung.
    Http::fake(['*/adcreatives' => Http::response(['id' => 'cr1'])]);

    app(MetaAdsService::class)->createVideoCreative('DYNOADS-1-1-21SEP26', 'v777', 'Ayat iklan.', 'https://cdn/t.jpg');

    Http::assertSent(function (Request $r) {
        $spec = json_decode($r->data()['object_story_spec'], true);

        return isset($spec['video_data'])
            && ! isset($spec['link_data'])
            && $spec['video_data']['video_id'] === 'v777'
            && $spec['video_data']['image_url'] === 'https://cdn/t.jpg'
            && $spec['video_data']['message'] === 'Ayat iklan.'
            && $spec['video_data']['call_to_action']['type'] === 'WHATSAPP_MESSAGE';
    });
});

it('creative video tetap dibuat walaupun Meta tiada thumbnail', function () {
    Http::fake(['*/adcreatives' => Http::response(['id' => 'cr1'])]);

    app(MetaAdsService::class)->createVideoCreative('nama', 'v1', 'Ayat.', null);

    Http::assertSent(function (Request $r) {
        $spec = json_decode($r->data()['object_story_spec'], true);

        // image_url ditapis keluar, bukan dihantar sebagai null — Meta menolak
        // medan null dengan ralat yang mengelirukan.
        return ! array_key_exists('image_url', $spec['video_data']);
    });
});

// ---------------------------------------------------------- pemprosesan

it('menunggu Meta siap memproses sebelum membuat creative', function () {
    // /advideos pulangkan id serta-merta, tetapi creative yang dibuat sebelum
    // pemprosesan selesai ditolak. Tanpa menunggu, iklan video gagal secara
    // rawak bergantung pada saiz fail dan beban Meta.
    Http::fake([
        '*/v1*' => Http::sequence()
            ->push(['status' => ['video_status' => 'processing']])
            ->push(['status' => ['video_status' => 'ready'], 'picture' => 'https://cdn/t.jpg']),
    ]);

    $ready = app(MetaAdsService::class)->waitForVideo('v1', timeoutSeconds: 30, pollSeconds: 1);

    expect($ready['status'])->toBe('ready')
        ->and($ready['thumbnail'])->toBe('https://cdn/t.jpg');
});

it('video yang Meta gagal proses memberi ayat yang boleh difahami', function () {
    Http::fake(['*/v1*' => Http::response(['status' => ['video_status' => 'error']])]);

    expect(fn () => app(MetaAdsService::class)->waitForVideo('v1', timeoutSeconds: 5, pollSeconds: 1))
        ->toThrow(MetaApiException::class, 'Meta gagal memproses video ini');
});

it('berhenti menunggu selepas had masa, dan kata video itu tidak hilang', function () {
    Http::fake(['*/v1*' => Http::response(['status' => ['video_status' => 'processing']])]);

    expect(fn () => app(MetaAdsService::class)->waitForVideo('v1', timeoutSeconds: 0, pollSeconds: 1))
        ->toThrow(MetaApiException::class, 'masih diproses');
});

// ------------------------------------------------------- enjin poster dibuang

it('skrin poster tiada lagi', function () {
    $this->get('/poster')->assertNotFound();

    expect(Route::has('posters.create'))->toBeFalse()
        ->and(Route::has('ad-sets.create'))->toBeTrue();
});

it('kod enjin poster betul-betul dibuang, bukan sekadar disembunyikan', function () {
    foreach ([
        'App\Livewire\Posters\Create',
        'App\Services\Poster\PosterService',
        'App\Services\Poster\PosterBasket',
        'App\Services\Poster\Backgrounds\AiDriver',
        'App\Providers\PosterServiceProvider',
    ] as $class) {
        expect(class_exists($class))->toBeFalse("{$class} masih wujud");
    }

    expect(is_dir(app_path('Services/Poster')))->toBeFalse()
        ->and(is_dir(resource_path('views/posters')))->toBeFalse();
});

it('sejarah poster dikekalkan — campaign lama masih boleh dibandingkan', function () {
    // Enjin dibuang, bukan datanya. Campaign poster yang sudah berjalan
    // angkanya masih bermakna untuk Fasa 5.
    $set = AdSet::factory()->create();
    $lama = AdVariant::factory()->for($set, 'adSet')->create(['source_type' => 'poster']);

    expect($lama->isPoster())->toBeTrue()
        ->and(Schema::hasTable('poster_jobs'))->toBeTrue();
});
