<?php

use App\Models\AdSet;
use App\Models\DataDeletionRequest;
use App\Models\FbConnection;
use App\Models\User;

/**
 * Callback pemadaman data Meta.
 *
 * Endpoint ini terdedah kepada internet terbuka tanpa log masuk dan tanpa
 * CSRF. Satu-satunya yang menjaganya ialah tandatangan HMAC. Setiap test di
 * sini menjawab satu soalan: bolehkah orang asing memadam data peniaga?
 */
const RAHSIA = 'rahsia-app-meta-untuk-ujian';

beforeEach(fn () => config()->set('dynoads.meta.app_secret', RAHSIA));

function signedRequest(array $payload, ?string $secret = null): string
{
    $encoded = rtrim(strtr(base64_encode(json_encode(
        $payload + ['algorithm' => 'HMAC-SHA256']
    )), '+/', '-_'), '=');

    $signature = hash_hmac('sha256', $encoded, $secret ?? RAHSIA, true);

    return rtrim(strtr(base64_encode($signature), '+/', '-_'), '=').'.'.$encoded;
}

// ------------------------------------------------------------- jalan betul

it('memadam data Facebook dan pulangkan url serta kod pengesahan', function () {
    $user = User::factory()->create();
    FbConnection::factory()->for($user)->create(['meta_user_id' => 'fb-123']);

    $response = $this->postJson('/meta/data-deletion', [
        'signed_request' => signedRequest(['user_id' => 'fb-123']),
    ]);

    // Meta mewajibkan kedua-dua medan ini. Tanpanya ia menganggap permintaan
    // gagal dan mencubanya berulang kali.
    $response->assertOk()
        ->assertJsonStructure(['url', 'confirmation_code']);

    expect(FbConnection::where('meta_user_id', 'fb-123')->exists())->toBeFalse();

    $record = DataDeletionRequest::firstOrFail();

    expect($record->status)->toBe('completed')
        ->and($record->user_id)->toBe($user->id)
        ->and($response->json('url'))->toContain($record->confirmation_code);
});

it('tidak memadam rekod perniagaan peniaga, hanya data Facebook', function () {
    // Set iklan dan angka prestasi ialah rekod peniaga sendiri. Memusnahkannya
    // atas satu callback akan menghapuskan sejarah yang dia mungkin perlukan.
    $user = User::factory()->create();
    FbConnection::factory()->for($user)->create(['meta_user_id' => 'fb-123']);

    $this->actingAs($user);
    AdSet::factory()->create(['name' => 'Iklan lama']);
    auth()->logout();

    $this->postJson('/meta/data-deletion', [
        'signed_request' => signedRequest(['user_id' => 'fb-123']),
    ])->assertOk();

    $this->actingAs($user->refresh());

    expect(AdSet::pluck('name')->all())->toBe(['Iklan lama']);
});

it('permintaan untuk akaun yang tiada dalam sistem tetap dijawab dengan betul', function () {
    $this->postJson('/meta/data-deletion', [
        'signed_request' => signedRequest(['user_id' => 'tak-pernah-wujud']),
    ])->assertOk()->assertJsonStructure(['url', 'confirmation_code']);

    expect(DataDeletionRequest::firstOrFail()->status)->toBe('no_match');
});

// --------------------------------------------------------------- keselamatan

it('menolak tandatangan yang salah dan TIDAK memadam apa-apa', function () {
    FbConnection::factory()->create(['meta_user_id' => 'fb-123']);

    $this->postJson('/meta/data-deletion', [
        'signed_request' => signedRequest(['user_id' => 'fb-123'], 'rahsia-yang-salah'),
    ])->assertStatus(400);

    expect(FbConnection::where('meta_user_id', 'fb-123')->exists())->toBeTrue()
        ->and(DataDeletionRequest::count())->toBe(0);
});

it('menolak muatan yang diusik walaupun tandatangan asalnya sah', function () {
    FbConnection::factory()->create(['meta_user_id' => 'mangsa']);

    // Tandatangan sah untuk 'penyerang', muatan ditukar kepada 'mangsa'.
    $sah = signedRequest(['user_id' => 'penyerang']);
    [$tandatangan] = explode('.', $sah);
    $muatanBaharu = rtrim(strtr(base64_encode(json_encode([
        'user_id' => 'mangsa', 'algorithm' => 'HMAC-SHA256',
    ])), '+/', '-_'), '=');

    $this->postJson('/meta/data-deletion', [
        'signed_request' => $tandatangan.'.'.$muatanBaharu,
    ])->assertStatus(400);

    expect(FbConnection::where('meta_user_id', 'mangsa')->exists())->toBeTrue();
});

it('menolak semua permintaan bila rahsia app tiada — gagal tertutup', function () {
    // Tanpa rahsia, tiada permintaan boleh disahkan. Endpoint yang tidak
    // berfungsi jauh lebih baik daripada endpoint yang memadam data atas
    // arahan sesiapa yang tahu URL.
    config()->set('dynoads.meta.app_secret', null);

    FbConnection::factory()->create(['meta_user_id' => 'fb-123']);

    $this->postJson('/meta/data-deletion', [
        'signed_request' => signedRequest(['user_id' => 'fb-123']),
    ])->assertStatus(400);

    expect(FbConnection::where('meta_user_id', 'fb-123')->exists())->toBeTrue();
});

it('menolak permintaan kosong atau tidak berbentuk', function () {
    foreach (['', 'bukan-signed-request', 'a.b', '.'] as $jahat) {
        $this->postJson('/meta/data-deletion', ['signed_request' => $jahat])
            ->assertStatus(400);
    }

    expect(DataDeletionRequest::count())->toBe(0);
});

it('mesej ralat tidak memberitahu penyerang apa yang salah', function () {
    $response = $this->postJson('/meta/data-deletion', [
        'signed_request' => signedRequest(['user_id' => 'x'], 'rahsia-salah'),
    ]);

    expect($response->json('error'))->toBe('Permintaan tidak sah.')
        ->and(json_encode($response->json()))
        ->not->toContain('tandatangan')
        ->not->toContain(RAHSIA);
});

it('endpoint tidak memerlukan token CSRF — Meta tiada sesi', function () {
    FbConnection::factory()->create(['meta_user_id' => 'fb-123']);

    // Tanpa pengecualian CSRF ini akan jadi 419, bukan 200.
    $this->post('/meta/data-deletion', [
        'signed_request' => signedRequest(['user_id' => 'fb-123']),
    ])->assertOk();
});

it('kod pengesahan tidak boleh diteka', function () {
    $kod = collect(range(1, 20))->map(fn () => DataDeletionRequest::newCode());

    expect($kod->unique())->toHaveCount(20)
        ->and(strlen($kod->first()))->toBeGreaterThanOrEqual(32);
});

// ------------------------------------------------------------ halaman status

it('halaman status memaparkan keputusan tanpa perlu log masuk', function () {
    $record = DataDeletionRequest::create([
        'confirmation_code' => 'kod-ujian-1234567890123456789012',
        'meta_user_id' => 'fb-123',
        'status' => 'completed',
        'summary' => 'Sambungan Facebook dipadam.',
        'completed_at' => now(),
    ]);

    $this->get(route('meta.data-deletion.status', $record->confirmation_code))
        ->assertOk()
        ->assertSee('Selesai')
        ->assertSee('Sambungan Facebook dipadam.');
});

it('kod yang tidak wujud tidak mendedahkan apa-apa', function () {
    $this->get(route('meta.data-deletion.status', 'kod-yang-direka-reka'))
        ->assertOk()
        ->assertSee('tidak dijumpai');
});
