<?php

use App\Exceptions\MetaCredentialsMissing;
use App\Livewire\Auth\Register;
use App\Models\AdSet;
use App\Models\AdVariant;
use App\Models\FbConnection;
use App\Models\PosterJob;
use App\Models\User;
use App\Services\Meta\MetaCredentials;
use App\Services\MetaAdsService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * Fasa 7a — app ini boleh melayan lebih daripada seorang peniaga.
 *
 * Setiap test di sini menjawab satu soalan yang mesti dijawab sebelum app ni
 * boleh dijual: bolehkah seorang peniaga nampak, ubah, atau membelanjakan duit
 * peniaga lain?
 */

// ------------------------------------------------------- pengasingan data

it('peniaga tidak nampak ad set peniaga lain', function () {
    $ali = User::factory()->create();
    $siti = User::factory()->create();

    $this->actingAs($ali);
    AdSet::factory()->create(['name' => 'Iklan Ali']);

    $this->actingAs($siti);
    AdSet::factory()->create(['name' => 'Iklan Siti']);

    expect(AdSet::pluck('name')->all())->toBe(['Iklan Siti']);

    $this->actingAs($ali);
    expect(AdSet::pluck('name')->all())->toBe(['Iklan Ali']);
});

it('user_id diisi sendiri daripada user yang log masuk', function () {
    $ali = User::factory()->create();
    $this->actingAs($ali);

    expect(AdSet::factory()->create()->user_id)->toBe($ali->id)
        ->and(PosterJob::create(['template' => 'promo-meletup', 'data' => [], 'cache_key' => 'k'])->user_id)
        ->toBe($ali->id);
});

it('user_id tidak boleh ditetapkan melalui mass assignment', function () {
    $ali = User::factory()->create();
    $siti = User::factory()->create();
    $this->actingAs($ali);

    // Kalau user_id fillable, satu medan tersembunyi dalam borang sudah cukup
    // untuk seorang peniaga menyisipkan data ke dalam akaun peniaga lain.
    $set = AdSet::create([
        'name' => 'Cubaan', 'problem' => 'x', 'offer' => 'y', 'phone' => '60123456789',
        'daily_budget_sen' => 3700, 'user_id' => $siti->id,
    ]);

    expect($set->user_id)->toBe($ali->id);
});

it('membuka ad set peniaga lain memberi 404, bukan 403', function () {
    // 403 mengesahkan id itu wujud. 404 tidak memberitahu apa-apa.
    $siti = pemilik();
    $this->actingAs($siti);
    $milikSiti = AdSet::factory()->create();

    $this->actingAs(pemilik());

    $this->get("/semak/{$milikSiti->id}")->assertNotFound();
    $this->get("/dashboard/{$milikSiti->id}")->assertNotFound();
    $this->get("/run/{$milikSiti->id}")->assertNotFound();
});

it('anak ad set mengikut pemilikan induknya', function () {
    $ali = User::factory()->create();
    $this->actingAs($ali);
    $set = AdSet::factory()->create();
    AdVariant::factory()->for($set, 'adSet')->create();

    $this->actingAs(User::factory()->create());

    expect(AdSet::count())->toBe(0)
        ->and(AdVariant::whereHas('adSet')->count())->toBe(0);
});

// ------------------------------------------------------------------ route

it('pelawat yang belum masuk dihantar ke skrin masuk', function () {
    foreach (['/', '/buat', '/posting', '/poster', '/sambung'] as $url) {
        $this->get($url)->assertRedirect(route('masuk'));
    }
});

it('peniaga tanpa sambungan Meta dihantar ke skrin sambung', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/buat')->assertRedirect(route('meta.connect'));
    $this->get('/posting')->assertRedirect(route('meta.connect'));
});

it('peniaga yang sudah menyambung boleh terus masuk', function () {
    $user = User::factory()->create();
    FbConnection::factory()->for($user)->create();

    $this->actingAs($user)->get('/sambung')->assertOk();
});

// ------------------------------------------------------------- kredential

it('peniaga bukan pemilik TIDAK PERNAH jatuh balik ke kredential .env', function () {
    // Ini pagar paling penting dalam keseluruhan Fasa 7a. Tanpanya, sesiapa
    // yang mendaftar akan membuat iklan di atas ad account pemilik app dan
    // membelanjakan duit pemilik. Peraturan mutlak #6.
    $peniaga = User::factory()->create(['is_owner' => false]);

    expect(fn () => MetaCredentials::forUser($peniaga))
        ->toThrow(MetaCredentialsMissing::class);
});

it('pemilik jatuh balik ke .env bila belum menyambung', function () {
    $credentials = MetaCredentials::forUser(pemilik());

    expect($credentials->source)->toBe('env')
        ->and($credentials->adAccountId)->toBe(config('dynoads.meta.ad_account_id'))
        ->and($credentials->pageId)->toBe((string) config('dynoads.meta.page_id'));
});

it('sambungan peniaga mengatasi .env walaupun untuk pemilik', function () {
    $owner = pemilik();
    FbConnection::factory()->for($owner)->create([
        'ad_account_id' => 'act_999', 'page_id' => '888', 'wa_phone' => '60111111111',
    ]);

    $credentials = MetaCredentials::forUser($owner->refresh());

    expect($credentials->source)->toBe('connection')
        ->and($credentials->adAccountId)->toBe('act_999')
        ->and($credentials->pageId)->toBe('888');
});

it('sambungan yang tidak aktif diabaikan', function () {
    $peniaga = User::factory()->create();
    FbConnection::factory()->for($peniaga)->create(['status' => 'revoked']);

    expect(fn () => MetaCredentials::forUser($peniaga->refresh()))
        ->toThrow(MetaCredentialsMissing::class);
});

it('token disimpan berenkripsi, bukan teks biasa', function () {
    $user = User::factory()->create();
    FbConnection::factory()->for($user)->create(['token' => 'TOKEN-RAHSIA-SANGAT-PANJANG']);

    $mentah = (string) DB::table('fb_connections')->value('token');

    expect($mentah)->not->toContain('TOKEN-RAHSIA-SANGAT-PANJANG')
        ->and($user->fbConnection->token)->toBe('TOKEN-RAHSIA-SANGAT-PANJANG');
});

it('token tidak pernah muncul dalam dump kredential — peraturan #8', function () {
    $dump = (new MetaCredentials('TOKEN-RAHSIA', 'act_1', '2', '60123456789', 'connection'))->__debugInfo();

    expect($dump['token'])->toBe('[disembunyikan]')
        ->and(json_encode($dump))->not->toContain('TOKEN-RAHSIA');
});

// ------------------------------------------- kredential sampai ke Meta

it('page_id dan nombor WhatsApp peniaga sampai ke payload Meta', function () {
    Http::fake(['*/adsets' => Http::response(['id' => 's1'])]);

    $meta = MetaAdsService::fromCredentials(
        new MetaCredentials('T', 'act_555', '777', '60199998888', 'connection')
    );

    $meta->createAdSet('c1', 'DYNOADS-1-1-21SEP26');

    Http::assertSent(function (Request $r) {
        $promoted = json_decode($r->data()['promoted_object'], true);

        return str_contains($r->url(), '/act_555/adsets')
            && $promoted['page_id'] === '777'
            && $promoted['whatsapp_phone_number'] === '60199998888';
    });
});

it('nombor WhatsApp yang peniaga taip untuk set ini mengatasi nombor sambungan', function () {
    // Bug sebenar sebelum Fasa 7a: ad_sets.phone disimpan tetapi tidak pernah
    // dihantar, jadi setiap lead sampai ke WhatsApp pemilik app.
    Http::fake(['*/adsets' => Http::response(['id' => 's1'])]);

    MetaAdsService::fromCredentials(
        new MetaCredentials('T', 'act_555', '777', '60111111111', 'connection')
    )->createAdSet('c1', 'nama', [], '60122223333');

    Http::assertSent(function (Request $r) {
        $promoted = json_decode($r->data()['promoted_object'], true);

        return $promoted['whatsapp_phone_number'] === '60122223333';
    });
});

it('object_story_id guna page peniaga, bukan page dalam config', function () {
    $meta = MetaAdsService::fromCredentials(
        new MetaCredentials('T', 'act_1', '424242', '60123456789', 'connection')
    );

    expect($meta->objectStoryId('101'))->toBe('424242_101')
        ->and($meta->objectStoryId('424242_101'))->toBe('424242_101')
        ->and('424242')->not->toBe((string) config('dynoads.meta.page_id'));
});

// -------------------------------------------------------------- pendaftaran

it('borang daftar tidak boleh mencipta pemilik', function () {
    Livewire::test(Register::class)
        ->set('name', 'Ali')
        ->set('email', 'ali@contoh.com')
        ->set('phone', '012-345 6789')
        ->set('password', 'kata-laluan-panjang')
        ->call('daftar');

    $user = User::where('email', 'ali@contoh.com')->firstOrFail();

    expect($user->is_owner)->toBeFalse()
        ->and($user->phone)->toBe('60123456789')
        ->and($user->mayUseEnvCredentials())->toBeFalse();
});

it('kata laluan disimpan sebagai hash', function () {
    $user = User::factory()->create(['password' => 'kata-laluan-ujian']);

    expect($user->password)->not->toBe('kata-laluan-ujian')
        ->and(password_verify('kata-laluan-ujian', $user->password))->toBeTrue();
});

// ------------------------------------------------------- arahan pemilik

it('dynoads:owner menuntut data yang belum bertuan', function () {
    $lama = AdSet::factory()->create();          // tiada user log masuk → user_id null
    expect($lama->user_id)->toBeNull();

    $this->artisan('dynoads:owner', [
        'email' => 'pemilik@dynopos.my',
        '--name' => 'Borhan',
        '--password' => 'kata-laluan-panjang',
    ])->assertSuccessful();

    $owner = User::where('email', 'pemilik@dynopos.my')->firstOrFail();

    expect($owner->is_owner)->toBeTrue()
        ->and($lama->refresh()->user_id)->toBe($owner->id);
});

it('dynoads:owner tidak menuntut data peniaga lain', function () {
    $siti = User::factory()->create();
    $this->actingAs($siti);
    $milikSiti = AdSet::factory()->create();

    auth()->logout();

    $this->artisan('dynoads:owner', [
        'email' => 'pemilik@dynopos.my',
        '--password' => 'kata-laluan-panjang',
    ])->assertSuccessful();

    expect($milikSiti->refresh()->user_id)->toBe($siti->id);
});
