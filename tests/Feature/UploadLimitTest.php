<?php

use App\Livewire\AdSets\Create;
use App\Support\Media;
use App\Support\Uploads;
use Illuminate\Http\UploadedFile;

/**
 * Peraturan validasi yang lebih longgar daripada php.ini adalah janji yang app
 * tidak boleh tunaikan. PHP menolak fail sebelum Laravel melihatnya, dan
 * peniaga dapat "The product failed to upload." — mesej Inggeris mentah yang
 * tidak memberitahu apa-apa.
 */
it('membaca had sebenar pelayan, bukan nombor yang kita reka', function () {
    // Yang paling ketat antara dua tetapan PHP yang menentukan.
    $expected = min(
        (int) round(ini_parse_quantity(ini_get('upload_max_filesize')) / 1024),
        (int) round(ini_parse_quantity(ini_get('post_max_size')) / 1024),
    );

    expect(Uploads::maxKilobytes(1_000_000))->toBe($expected);
});

it('tidak pernah menjanjikan lebih daripada siling yang diminta', function () {
    expect(Uploads::maxKilobytes(1))->toBe(1);
});

it('memaparkan had dalam bentuk yang peniaga faham', function () {
    expect(Uploads::maxLabel())->toMatch('/^[\d.]+ (KB|MB)$/');
});

it('mengesan bila had terlalu ketat untuk gambar telefon', function () {
    // Gambar telefon biasa 3-8 MB. Apa-apa di bawah 4 MB akan menggagalkan
    // kebanyakan muat naik, jadi peniaga patut diberitahu sebelum mencuba.
    expect(Uploads::tooTightForPhonePhotos())->toBe(Uploads::maxKilobytes() < 4096);
});

it('had video tidak pernah melebihi had PHP', function () {
    // config boleh kata 100 MB, tetapi PHP yang menentukan. Menjanjikan lebih
    // daripada yang pelayan benarkan bermakna peniaga menunggu muat naik yang
    // memang tidak akan menjadi.
    expect(Uploads::maxKilobytes(Media::maxVideoKilobytes()))
        ->toBeLessThanOrEqual(Uploads::maxKilobytes(PHP_INT_MAX));
});

it('fail yang terlalu besar ditolak dengan sebab dalam Bahasa Melayu', function () {
    $besar = UploadedFile::fake()->create('video.mp4', Uploads::maxKilobytes(Media::maxVideoKilobytes()) + 1024);

    expect(Media::reject($besar))
        ->toContain('terlalu besar')
        ->toContain('video.mp4')
        ->not->toContain('failed to upload');
});

it('jenis fail yang tidak boleh diiklankan ditolak awal', function () {
    expect(Media::reject(UploadedFile::fake()->create('katalog.pdf', 10)))
        ->toContain('bukan gambar atau video');
});

it('gambar dan video kedua-duanya diterima', function () {
    expect(Media::reject(UploadedFile::fake()->image('kedai.jpg')))->toBeNull()
        ->and(Media::reject(UploadedFile::fake()->create('kedai.mp4', 200)))->toBeNull();
});

it('kegagalan muat naik dijelaskan dalam Bahasa Melayu', function () {
    $messages = (new ReflectionMethod(Create::class, 'messages'))
        ->invoke(app(Create::class));

    expect($messages)->toHaveKey('upload.*.uploaded')
        ->and($messages['upload.*.uploaded'])
        ->toContain('gagal dimuat naik')
        ->toContain(Uploads::maxLabel())
        ->not->toContain('failed to upload');
});
