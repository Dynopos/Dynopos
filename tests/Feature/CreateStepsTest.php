<?php

use App\Livewire\AdSets\Create;
use App\Models\FbConnection;
use App\Models\User;
use App\Support\Uploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * Peraturan #10: satu skrin satu keputusan. Borang /buat dipecah kepada
 * empat langkah, tetapi kekal satu komponen dan satu save().
 */
beforeEach(fn () => Http::fake(['graph.facebook.com/*' => Http::response(['data' => []])]));

it('tidak boleh ke langkah 2 tanpa media', function () {
    masukSebagaiPemilik();

    Livewire::test(Create::class)
        ->call('next')
        ->assertHasErrors('media')
        ->assertSet('step', 1);
});

it('setiap langkah hanya mengesahkan medannya sendiri', function () {
    masukSebagaiPemilik();

    Livewire::test(Create::class)
        ->set('upload', [UploadedFile::fake()->image('a.jpg', 1200, 1200)])
        ->call('next')
        ->assertSet('step', 2)
        ->assertHasNoErrors()
        // Langkah 2: bisnes baharu tanpa penerangan ditolak; masalah belum ditanya.
        ->set('businessId', null)
        ->set('businessName', 'Ali Skylift')
        ->call('next')
        ->assertHasErrors('businessDescription')
        ->assertHasNoErrors('problem')
        ->assertSet('step', 2)
        ->set('businessDescription', 'Sewa skylift untuk kerja tempat tinggi di Kelantan.')
        ->call('next')
        ->assertSet('step', 3)
        ->call('back')
        ->assertSet('step', 2);
});

it('save membawa peniaga ke langkah yang ada ralat', function () {
    masukSebagaiPemilik();

    Livewire::test(Create::class)
        ->set('upload', [UploadedFile::fake()->image('a.jpg', 1200, 1200)])
        ->set('step', 4)
        ->set('businessId', null)
        ->set('businessName', 'Ali Skylift')
        ->set('businessDescription', 'Sewa skylift untuk kerja tempat tinggi di Kelantan.')
        ->set('problem', '')
        ->set('offer', 'Sewa skylift harian')
        ->call('save')
        ->assertHasErrors('problem')
        ->assertSet('step', 3);
});

it('nota teknikal pelayan tidak dipapar kepada peniaga', function () {
    $peniaga = User::factory()->create();
    FbConnection::factory()->for($peniaga)->create();
    $this->actingAs($peniaga);

    $component = Livewire::test(Create::class);

    expect($component->instance()->showsServerNotes())->toBeFalse();
    $component->assertDontSee('upload_max_filesize')->assertDontSee('META_ACCESS_TOKEN');
});

it('pemilik nampak nota teknikal pelayan', function () {
    masukSebagaiPemilik();

    $component = Livewire::test(Create::class);

    expect($component->instance()->showsServerNotes())->toBeTrue();

    if (Uploads::tooTightForPhonePhotos()) {
        $component->assertSee('upload_max_filesize');
    }
});
