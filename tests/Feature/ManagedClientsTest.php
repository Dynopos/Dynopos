<?php

use App\Exceptions\ConsentMissing;
use App\Exceptions\MetaCredentialsMissing;
use App\Livewire\Clients\Index as Clients;
use App\Livewire\Settings\Connect;
use App\Models\FbConnection;
use App\Models\User;
use App\Services\Meta\MetaCredentials;
use App\Support\Impersonation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * Servis urus iklan — pemilik mengurus pelanggan melalui Partner Access.
 *
 * Soalan yang setiap test jawab: bolehkah duit pelanggan dibelanjakan tanpa
 * persetujuan mereka, atau bolehkah token System User pemilik jatuh ke tangan
 * orang yang bukan pemilik?
 */
const TOKEN_PEMILIK = 'EAAB_TOKEN_SYSTEM_USER_PEMILIK_UNTUK_UJIAN_0123456789';

beforeEach(fn () => config()->set('dynoads.meta.token', TOKEN_PEMILIK));

function fakeVerifyAccess(): void
{
    Http::fake([
        'graph.facebook.com/*/act_*' => Http::response(['name' => 'Iklan Kak Mah', 'account_status' => 1]),
        'graph.facebook.com/*' => Http::response(['name' => 'Salon Kak Mah']),
    ]);
}

/** Pemilik sedang mengurus pelanggan baharu. */
function urusPelanggan(): array
{
    $owner = masukSebagaiPemilik();
    $client = User::factory()->create(['name' => 'Salon Kak Mah']);
    Impersonation::start($owner, $client);

    return [$owner, $client];
}

function sambungGunaTokenPemilik(): void
{
    Livewire::test(Connect::class)
        ->set('useOwnerToken', true)
        ->set('adAccountId', '999000222')
        ->set('pageId', '555000111')
        ->set('waPhone', '012-345 6789')
        ->call('sambung')
        ->assertSet('ralat', null);
}

// ------------------------------------------------------------- skrin pemilik

it('skrin pelanggan 404 untuk peniaga biasa', function () {
    $this->actingAs(User::factory()->create())->get('/pelanggan')->assertNotFound();
});

it('pemilik nampak senarai pelanggan dengan status setiap satu', function () {
    masukSebagaiPemilik();
    $siap = User::factory()->create(['name' => 'Kedai Siap']);
    FbConnection::factory()->for($siap)->create();
    $tunggu = User::factory()->create(['name' => 'Kedai Tunggu']);
    FbConnection::factory()->for($tunggu)->create(['managed_by_user_id' => pemilik()->id]);

    $this->get('/pelanggan')->assertOk()
        ->assertSee('Kedai Siap')->assertSee('Sedia untuk iklan')
        ->assertSee('Kedai Tunggu')->assertSee('Tunggu persetujuan bertulis');
});

it('pemilik boleh tambah pelanggan tanpa kata laluan yang diketahui sesiapa', function () {
    masukSebagaiPemilik();

    Livewire::test(Clients::class)
        ->set('name', 'Salon Kak Mah')
        ->set('email', 'kakmah@contoh.com')
        ->set('phone', '012-345 6789')
        ->call('tambah')
        ->assertHasNoErrors();

    $client = User::where('email', 'kakmah@contoh.com')->sole();

    expect($client->is_owner)->toBeFalse()
        ->and($client->phone)->toBe('60123456789');
});

it('pemilik urus pelanggan dan kembali tanpa log keluar', function () {
    $owner = masukSebagaiPemilik();
    $client = User::factory()->create(['name' => 'Salon Kak Mah']);

    Livewire::test(Clients::class)->call('urus', $client->id)->assertRedirect(route('meta.connect'));

    expect(Auth::id())->toBe($client->id)
        ->and(Impersonation::owner()?->id)->toBe($owner->id);

    $this->get('/sambung')->assertSee('Anda sedang urus')->assertSee('Salon Kak Mah');

    $this->post('/pelanggan/kembali')->assertRedirect(route('clients.index'));

    expect(Auth::id())->toBe($owner->id)
        ->and(Impersonation::active())->toBeFalse();
});

it('peniaga biasa tidak boleh mengurus akaun orang lain', function () {
    $peniaga = User::factory()->create();
    $mangsa = User::factory()->create();
    $this->actingAs($peniaga);

    expect(fn () => Impersonation::start($peniaga, $mangsa))->toThrow(InvalidArgumentException::class);
    expect(Auth::id())->toBe($peniaga->id);
});

it('akaun pemilik tidak boleh diurus sebagai pelanggan', function () {
    $owner = masukSebagaiPemilik();
    $lain = pemilik();

    expect(fn () => Impersonation::start($owner, $lain))->toThrow(InvalidArgumentException::class);
});

it('sesi yang menunjuk kepada bukan pemilik tidak dikira', function () {
    $peniaga = User::factory()->create();
    $this->actingAs(User::factory()->create());
    session()->put('pemilik_asal_id', $peniaga->id);

    expect(Impersonation::owner())->toBeNull();

    $this->post('/pelanggan/kembali');
    expect(Auth::id())->not->toBe($peniaga->id);
});

// ---------------------------------------------------- token pemilik + sambung

it('pemilik sambung pelanggan guna token .env tanpa menyalinnya', function () {
    fakeVerifyAccess();
    [$owner, $client] = urusPelanggan();

    sambungGunaTokenPemilik();

    $conn = FbConnection::where('user_id', $client->id)->sole();

    expect($conn->token)->toBeNull()
        ->and($conn->uses_owner_token)->toBeTrue()
        ->and($conn->managed_by_user_id)->toBe($owner->id)
        ->and($conn->ad_account_id)->toBe('act_999000222');

    Http::assertSent(fn ($r) => str_contains($r->url(), 'act_999000222')
        && ($r->data()['access_token'] ?? null) === TOKEN_PEMILIK);
});

it('peniaga biasa TIDAK boleh meminjam token pemilik', function () {
    Http::fake();
    $peniaga = User::factory()->create();
    $this->actingAs($peniaga);

    Livewire::test(Connect::class)
        ->set('useOwnerToken', true)
        ->set('adAccountId', '701202512896650')
        ->set('pageId', '377330642350146')
        ->set('waPhone', '012-345 6789')
        ->call('sambung')
        ->assertHasErrors('token');

    expect(FbConnection::count())->toBe(0);
    Http::assertNothingSent();
});

it('pelanggan yang sambung sendiri kemudian berhenti meminjam token pemilik', function () {
    fakeVerifyAccess();
    [, $client] = urusPelanggan();
    sambungGunaTokenPemilik();
    Impersonation::stop();

    $this->actingAs($client);
    Livewire::test(Connect::class)
        ->set('useOwnerToken', true)
        ->set('token', str_repeat('T', 60))
        ->set('adAccountId', '999000222')
        ->set('pageId', '555000111')
        ->set('waPhone', '012-345 6789')
        ->call('sambung');

    $conn = FbConnection::where('user_id', $client->id)->sole();

    expect($conn->uses_owner_token)->toBeFalse()
        ->and($conn->managed_by_user_id)->toBeNull()
        ->and($conn->token)->toBe(str_repeat('T', 60));
});

// ------------------------------------------------------------- persetujuan

it('tiada kredential untuk pelanggan urus selagi persetujuan belum direkod', function () {
    fakeVerifyAccess();
    [, $client] = urusPelanggan();
    sambungGunaTokenPemilik();

    expect(fn () => MetaCredentials::forUser($client->refresh()))->toThrow(ConsentMissing::class)
        ->and($client->hasMetaCredentials())->toBeFalse();

    $this->get('/buat')->assertRedirect(route('meta.connect'));
    $this->get('/posting')->assertRedirect(route('meta.connect'));
});

it('selepas persetujuan, kredential pelanggan guna token pemilik dan aset pelanggan', function () {
    fakeVerifyAccess();
    [, $client] = urusPelanggan();
    sambungGunaTokenPemilik();

    Livewire::test(Connect::class)
        ->set('consentNote', 'Balas Setuju di WhatsApp 3 Nov')
        ->call('rekodPersetujuan')
        ->assertHasNoErrors();

    $credentials = MetaCredentials::forUser($client->refresh());

    expect($credentials->token)->toBe(TOKEN_PEMILIK)
        ->and($credentials->adAccountId)->toBe('act_999000222')
        ->and($credentials->pageId)->toBe('555000111')
        ->and($client->fbConnection->consent_note)->toBe('Balas Setuju di WhatsApp 3 Nov');
});

it('persetujuan wajib ada bukti bertulis', function () {
    fakeVerifyAccess();
    [, $client] = urusPelanggan();
    sambungGunaTokenPemilik();

    Livewire::test(Connect::class)
        ->set('consentNote', '')
        ->call('rekodPersetujuan')
        ->assertHasErrors('consentNote');

    expect($client->fbConnection()->first()->consented_at)->toBeNull();
});

it('pelanggan tidak boleh merekod persetujuan untuk dirinya tanpa pemilik', function () {
    $client = User::factory()->create();
    FbConnection::factory()->for($client)->create([
        'managed_by_user_id' => pemilik()->id, 'uses_owner_token' => true, 'token' => null,
    ]);
    $this->actingAs($client);

    Livewire::test(Connect::class)
        ->set('consentNote', 'Saya setuju sendiri')
        ->call('rekodPersetujuan');

    expect($client->fbConnection()->first()->consented_at)->toBeNull();
});

it('sambungan yang peniaga buat sendiri tidak perlu rekod persetujuan', function () {
    $peniaga = User::factory()->create();
    FbConnection::factory()->for($peniaga)->create();

    expect(MetaCredentials::forUser($peniaga->refresh())->source)->toBe('connection');
});

it('token .env kosong menghentikan pelanggan urus, bukan menghantar token kosong', function () {
    $client = User::factory()->create();
    FbConnection::factory()->for($client)->create([
        'managed_by_user_id' => pemilik()->id, 'uses_owner_token' => true, 'token' => null,
        'consented_at' => now(), 'consent_note' => 'Setuju',
    ]);
    config()->set('dynoads.meta.token', '');

    expect(fn () => MetaCredentials::forUser($client->refresh()))
        ->toThrow(MetaCredentialsMissing::class);
});
