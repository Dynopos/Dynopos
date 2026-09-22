<?php

use App\Livewire\AdSets\Create;
use App\Livewire\AdSets\Dashboard;
use App\Livewire\AdSets\Review;
use App\Livewire\AdSets\Run;
use App\Models\AdSet;
use App\Models\AdVariant;
use App\Models\MetricDaily;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    config()->set('dynoads.caption.openai.api_key', 'sk-ujian');

    Http::fake([
        '*/search*' => Http::response(['data' => [
            ['key' => '3847', 'name' => 'Selangor'],
            ['key' => '3846', 'name' => 'Johor'],
        ]]),
        '*/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => '["Caption satu.", "Caption dua."]']]],
        ]),
        '*/adimages' => Http::response(['images' => ['1.jpg' => ['hash' => 'hash123']]]),
        '*/campaigns' => Http::response(['id' => 'c1']),
        '*/adsets' => Http::response(['id' => 's1']),
        '*/adcreatives' => Http::response(['id' => 'cr1']),
        '*/ads' => Http::response(['id' => 'a1']),
        '*/insights*' => Http::response(['data' => [[
            'spend' => '40.00', 'impressions' => '1000', 'clicks' => '20', 'ctr' => '2.0',
            'actions' => [['action_type' => 'onsite_conversion.total_messaging_connection', 'value' => '4']],
        ]]]),
        '*' => Http::response(['success' => true]),
    ]);
});

it('/buat menerima 2 gambar dan menyimpan set dengan variant siap crop', function () {
    Livewire::test(Create::class)
        ->set('upload', [
            UploadedFile::fake()->image('a.jpg', 1600, 900),
            UploadedFile::fake()->image('b.jpg', 900, 1600),
        ])
        ->set('problem', 'kira duit lambat waktu peak hour')
        ->set('offer', 'sistem POS fullset, pasang di kedai')
        ->set('phone', '60187922844')
        ->set('budgetRm', 20)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $set = AdSet::firstOrFail();

    expect($set->daily_budget_sen)->toBe(2000)
        ->and($set->variants)->toHaveCount(2)
        ->and($set->regionLabel())->toBe('Seluruh Malaysia');

    $path = Storage::disk('public')->path($set->variants->first()->image_path);

    expect(getimagesize($path))->toMatchArray([0 => 1080, 1 => 1080]);
});

it('/buat menolak lebih dari 4 gambar dan nombor telefon salah format', function () {
    Livewire::test(Create::class)
        ->set('media', collect(range(1, 5))->map(fn ($i) => UploadedFile::fake()->image("{$i}.jpg"))->all())
        ->set('upload', [])
        ->set('problem', 'kira duit lambat')
        ->set('offer', 'sistem POS')
        ->set('phone', '+60187922844')
        ->call('save')
        ->assertHasErrors(['media', 'phone']);

    expect(AdSet::count())->toBe(0);
});

it('memilih gambar satu-satu MENAMBAH, bukan menggantikan', function () {
    // Atas telefon, galeri selalunya pulangkan satu gambar setiap kali. Kalau
    // pilihan kedua menggantikan yang pertama, peniaga hanya dapat satu iklan
    // tanpa tahu kenapa. Itu bug yang ujian ini menjaga.
    Livewire::test(Create::class)
        ->set('upload', [UploadedFile::fake()->image('satu.jpg')])
        ->assertCount('media', 1)
        ->set('upload', [UploadedFile::fake()->image('dua.jpg')])
        ->assertCount('media', 2)
        ->set('upload', [UploadedFile::fake()->image('tiga.jpg')])
        ->assertCount('media', 3)
        // Kotak pilih fail dikosongkan supaya pilihan seterusnya bermula bersih.
        ->assertCount('upload', 0);
});

it('tidak menerima gambar melebihi had walaupun dipilih berkali-kali', function () {
    $component = Livewire::test(Create::class);

    foreach (range(1, 6) as $i) {
        $component->set('upload', [UploadedFile::fake()->image("{$i}.jpg")]);
    }

    $component->assertCount('media', config('dynoads.creative.max_creatives'));
});

it('boleh membuang satu gambar dan nombor iklan disusun semula', function () {
    Livewire::test(Create::class)
        ->set('upload', [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
            UploadedFile::fake()->image('c.jpg'),
        ])
        ->call('removeMedia', 1)
        ->assertCount('media', 2);
});

it('boleh pilih beberapa negeri, dan Meta terima kesemuanya', function () {
    Livewire::test(Create::class)
        ->call('toggleRegion', '3847')
        ->call('toggleRegion', '3846')
        ->assertSet('regionKeys', ['3847', '3846'])
        // Tekan sekali lagi = buang.
        ->call('toggleRegion', '3847')
        ->assertSet('regionKeys', ['3846'])
        ->set('upload', [UploadedFile::fake()->image('a.jpg')])
        ->set('problem', 'kira duit lambat waktu peak hour')
        ->set('offer', 'sistem POS fullset, pasang di kedai')
        ->set('phone', '60187922844')
        ->call('save')
        ->assertHasNoErrors();

    $set = AdSet::firstOrFail();

    expect($set->region_keys)->toBe(['3846'])
        ->and($set->region_names)->toBe(['Johor'])
        ->and($set->regionLabel())->toBe('Johor');
});

it('tiada negeri dipilih bermakna seluruh Malaysia', function () {
    Livewire::test(Create::class)
        ->set('upload', [UploadedFile::fake()->image('a.jpg')])
        ->set('problem', 'kira duit lambat waktu peak hour')
        ->set('offer', 'sistem POS fullset, pasang di kedai')
        ->set('phone', '60187922844')
        ->call('save')
        ->assertHasNoErrors();

    expect(AdSet::firstOrFail()->regionLabel())->toBe('Seluruh Malaysia');
});

it('/semak menjana caption, dan approve membuat campaign PAUSED sahaja', function () {
    $set = AdSet::factory()->create();

    foreach ([1, 2] as $position) {
        $path = "dynoads/{$set->id}/{$position}.jpg";
        Storage::disk('public')->put($path, UploadedFile::fake()->image('x.jpg', 1080, 1080)->get());

        AdVariant::factory()->for($set, 'adSet')->create([
            'position' => $position,
            'image_path' => $path,
            'caption' => null,
        ]);
    }

    Livewire::test(Review::class, ['adSet' => $set])
        ->assertSet('captions.'.$set->variants[0]->id, 'Caption satu.')
        ->call('approve')
        ->assertHasNoErrors()
        ->assertRedirect(route('ad-sets.run', $set));

    $set->refresh()->load('variants');

    expect($set->status)->toBe('created')
        ->and($set->variants->pluck('status')->all())->toBe(['paused', 'paused'])
        ->and($set->variants->pluck('meta_campaign_id')->all())->toBe(['c1', 'c1']);
});

it('/semak menolak caption yang mengandungi perkataan larangan', function () {
    $set = AdSet::factory()->create();
    $variant = AdVariant::factory()->for($set, 'adSet')->create();

    Livewire::test(Review::class, ['adSet' => $set])
        ->set("captions.{$variant->id}", 'Hasil dijamin dalam 7 hari')
        ->call('approve')
        ->assertSet('error', 'Ada perkataan yang Meta biasa tolak dalam caption. Ubah dulu.');

    expect($variant->refresh()->meta_campaign_id)->toBeNull();
});

it('/run menghidupkan semua iklan dan boleh pause satu-satu', function () {
    $set = AdSet::factory()->create(['status' => 'created']);
    $variant = AdVariant::factory()->for($set, 'adSet')->created()->create();

    Livewire::test(Run::class, ['adSet' => $set])
        ->call('runAll')
        ->assertSet('error', null);

    expect($variant->refresh()->status)->toBe('active')
        ->and($set->refresh()->status)->toBe('running');

    Livewire::test(Run::class, ['adSet' => $set->fresh()])
        ->call('pauseVariant', $variant->id);

    expect($variant->refresh()->status)->toBe('paused');
});

it('/dashboard memaparkan belanja, lead dan kos/lead selepas refresh', function () {
    $set = AdSet::factory()->create(['status' => 'running']);
    AdVariant::factory()->for($set, 'adSet')->created()->create(['status' => 'active']);

    Livewire::test(Dashboard::class, ['adSet' => $set])
        ->call('refreshMetrics')
        ->assertViewHas('totalSpendSen', 4000)
        ->assertViewHas('totalLeads', 4);

    expect(MetricDaily::count())->toBe(1);
});

it('halaman /buat boleh dibuka tanpa ralat', function () {
    masukSebagaiPemilik();

    $this->get('/buat')->assertOk()->assertSee('Buat iklan baru');
});

it('/ mengalihkan ke /buat', function () {
    masukSebagaiPemilik();

    $this->get('/')->assertRedirect('/buat');
});

it('tidak pernah memaparkan token bila senarai negeri gagal ditarik', function () {
    // Mesej pengecualian HTTP membawa URL penuh, dan URL Graph API mengandungi
    // ?access_token=. Peraturan mutlak #8.
    config()->set('dynoads.meta.token', 'EAAB_TOKEN_RAHSIA_UNTUK_UJIAN');
    Cache::flush();

    // beforeEach sudah daftar fake yang berjaya untuk */search*, dan stub
    // pertama yang menang — jadi mulakan dengan factory bersih.
    Http::swap(new Factory);

    Http::fake(['*/search*' => Http::response([
        'error' => ['message' => 'Invalid OAuth access token.', 'code' => 190],
    ], 401)]);

    $component = Livewire::test(Create::class);

    expect($component->get('regions'))->toBe([])
        ->and($component->get('regionError'))
        ->toBeString()
        ->not->toContain('EAAB_TOKEN_RAHSIA_UNTUK_UJIAN')
        ->not->toContain('access_token');

    $component->assertDontSee('EAAB_TOKEN_RAHSIA_UNTUK_UJIAN')
        ->assertDontSee('access_token');
});
