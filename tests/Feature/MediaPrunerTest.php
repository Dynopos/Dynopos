<?php

use App\Exceptions\MediaMissingException;
use App\Models\AdSet;
use App\Models\AdVariant;
use App\Models\AutoAction;
use App\Services\AdLauncher;
use App\Services\MediaPruner;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Pembersihan media.
 *
 * Video dibenarkan sehingga 100MB setiap fail dan sebelum ini tiada apa yang
 * memadamnya — setiap video kekal di cakera selamanya. Itu kos yang berkembang
 * tanpa had DAN Prinsip Penyimpanan PDPA yang tidak dipatuhi.
 *
 * Kelas ini memadam fail pengeluaran, jadi setiap test di sini menjawab satu
 * soalan: bolehkah ia memadam sesuatu yang sepatutnya kekal?
 */
beforeEach(fn () => Storage::fake('public'));

function setLama(int $hariLalu = 120, string $status = 'paused'): AdSet
{
    $set = AdSet::factory()->create();

    $variant = AdVariant::factory()->for($set, 'adSet')->create([
        'position' => 1,
        'media_type' => 'video',
        'image_path' => null,
        'video_path' => "dynoads/{$set->id}/1.mp4",
        'status' => $status,
        'meta_video_id' => 'v1',
        'meta_campaign_id' => 'c1',
        'meta_adset_id' => 's1',
        'meta_ad_id' => 'a1',
    ]);

    Storage::disk('public')->put($variant->video_path, str_repeat('x', 2048));

    // updated_at ditetapkan terakhir — setiap update di atas menolaknya semula.
    $set->forceFill(['updated_at' => now()->subDays($hariLalu)])->saveQuietly();

    return $set->refresh();
}

// ------------------------------------------------------------- yang dibuang

it('membuang fail set iklan yang sudah lama selesai', function () {
    $set = setLama();
    $path = $set->variants->first()->video_path;

    $hasil = app(MediaPruner::class)->prune($set);

    expect(Storage::disk('public')->exists($path))->toBeFalse()
        ->and($hasil['fail'])->toBe(1)
        ->and($hasil['bait'])->toBe(2048);
});

it('mengosongkan laluan dan merekod bila ia dibuang', function () {
    $set = setLama();

    app(MediaPruner::class)->prune($set);
    $variant = $set->variants()->first();

    expect($variant->video_path)->toBeNull()
        ->and($variant->image_path)->toBeNull()
        ->and($variant->isMediaPruned())->toBeTrue();
});

it('mengekalkan caption, angka prestasi dan sejarah campaign', function () {
    // Yang dibuang hanyalah fail besar. Baris ad_variants ialah satu-satunya
    // sebab peniaga boleh tahu creative mana yang menang tahun lepas.
    $set = setLama();
    $set->variants()->first()->update(['caption' => 'Ayat yang menang.']);

    app(MediaPruner::class)->prune($set->refresh());
    $variant = $set->variants()->first();

    expect($variant->exists)->toBeTrue()
        ->and($variant->caption)->toBe('Ayat yang menang.')
        ->and($variant->meta_campaign_id)->not->toBeNull()
        ->and($variant->meta_video_id)->toBe('v1');
});

it('merekod pembersihan dalam timeline supaya peniaga tahu', function () {
    $set = setLama();

    app(MediaPruner::class)->prune($set);

    $action = AutoAction::where('action', 'prune_media')->firstOrFail();

    expect($action->ad_set_id)->toBe($set->id)
        ->and($action->result)->toBe('ok')
        ->and($action->reason)->toContain('tempoh simpanan');
});

// ---------------------------------------------------------- yang DILINDUNGI

it('tidak pernah menyentuh set iklan yang masih ada iklan hidup', function () {
    // Peniaga melihat iklan ini dalam dashboard setiap hari. Ia berjalan
    // setahun pun, ia tetap tidak boleh disentuh.
    $set = setLama(hariLalu: 400, status: 'active');

    expect(app(MediaPruner::class)->candidates(90)->pluck('id'))
        ->not->toContain($set->id);
});

it('tidak menyentuh set iklan yang belum cukup umur', function () {
    $set = setLama(hariLalu: 30);

    expect(app(MediaPruner::class)->candidates(90)->pluck('id'))
        ->not->toContain($set->id);
});

it('tidak mengambil semula set iklan yang sudah dibersihkan', function () {
    $set = setLama();
    app(MediaPruner::class)->prune($set);

    expect(app(MediaPruner::class)->candidates(90)->pluck('id'))
        ->not->toContain($set->id);
});

it('menolak laluan yang menunjuk ke luar folder set iklan sendiri', function () {
    // Satu baris pangkalan data yang rosak tidak sepatutnya boleh menyuruh
    // kita memadam fail di tempat lain.
    $set = setLama();
    Storage::disk('public')->put('dynoads/999/milik-orang-lain.jpg', 'x');
    Storage::disk('public')->put('rahsia.jpg', 'x');

    $set->variants()->first()->forceFill([
        'image_path' => 'dynoads/999/milik-orang-lain.jpg',
        'video_path' => '../../rahsia.jpg',
    ])->save();

    app(MediaPruner::class)->prune($set->refresh());

    expect(Storage::disk('public')->exists('dynoads/999/milik-orang-lain.jpg'))->toBeTrue()
        ->and(Storage::disk('public')->exists('rahsia.jpg'))->toBeTrue();
});

// -------------------------------------------------------------------- dry run

it('dry run tidak memadam apa-apa tetapi tetap mengira', function () {
    $set = setLama();
    $path = $set->variants->first()->video_path;

    $hasil = app(MediaPruner::class)->prune($set, dryRun: true);

    expect($hasil['fail'])->toBe(1)
        ->and(Storage::disk('public')->exists($path))->toBeTrue()
        ->and($set->variants()->first()->isMediaPruned())->toBeFalse()
        ->and(AutoAction::where('action', 'prune_media')->count())->toBe(0);
});

// ------------------------------------------------------------------- arahan

it('arahan dynoads:prune-media berjalan dan melaporkan hasilnya', function () {
    $set = setLama();

    $this->artisan('dynoads:prune-media')
        ->expectsOutputToContain('1 set iklan')
        ->assertSuccessful();

    expect($set->variants()->first()->isMediaPruned())->toBeTrue();
});

it('arahan menolak tempoh simpanan yang tidak masuk akal', function () {
    $this->artisan('dynoads:prune-media', ['--days' => 0])->assertFailed();
});

// ------------------------------------------- melancarkan selepas dibersihkan

it('melancarkan set iklan yang medianya dibuang memberi ayat yang berguna', function () {
    // Tanpa pagar ini, peniaga dapat amaran file_get_contents mentah yang
    // tidak memberitahunya apa-apa.
    Http::fake(['*' => Http::response(['id' => 'x'])]);

    $set = setLama();
    app(MediaPruner::class)->prune($set);

    $variant = $set->variants()->first();
    $variant->update(['meta_video_id' => null, 'meta_ad_id' => null]);

    expect(fn () => app(AdLauncher::class)->createOne($set, $variant->refresh()))
        ->toThrow(MediaMissingException::class, 'sudah tiada');

    expect($set->variants()->first()->status)->toBe('failed');
});
