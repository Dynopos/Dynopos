<?php

use App\Livewire\AdSets\Create;
use App\Models\AdSet;
use App\Models\PosterJob;
use App\Services\Poster\Backgrounds\AiDriver;
use App\Services\Poster\Backgrounds\StockDriver;
use App\Services\Poster\PosterBasket;
use App\Services\Poster\PosterService;
use App\Services\Poster\ProductHandoff;
use App\Services\Poster\Removers\GdRemover;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** Gambar produk atas latar rata — seperti tangkapan atas meja putih. */
function produkAtasLatarRata(int $rgb = 0xFFFFFF): string
{
    $path = sys_get_temp_dir().'/ujian-rata-'.$rgb.'.png';
    $im = imagecreatetruecolor(600, 600);
    imagefill($im, 0, 0, imagecolorallocate($im, ($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255));
    imagefilledrectangle($im, 150, 150, 450, 450, imagecolorallocate($im, 20, 40, 90));
    imagepng($im, $path);
    imagedestroy($im);

    return $path;
}

/** Gambar produk atas latar sibuk — seperti tangkapan dalam kedai bersepah. */
function produkAtasLatarSibuk(): string
{
    $path = sys_get_temp_dir().'/ujian-sibuk.png';
    $im = imagecreatetruecolor(600, 600);
    mt_srand(7);
    for ($y = 0; $y < 600; $y += 5) {
        for ($x = 0; $x < 600; $x += 5) {
            imagefilledrectangle($im, $x, $y, $x + 5, $y + 5,
                imagecolorallocate($im, mt_rand(40, 220), mt_rand(40, 220), mt_rand(40, 220)));
        }
    }
    imagefilledrectangle($im, 150, 150, 450, 450, imagecolorallocate($im, 20, 40, 90));
    imagepng($im, $path);
    imagedestroy($im);

    return $path;
}

beforeEach(function () {
    Storage::fake('public');
    config()->set('dynoads.poster.disk', 'public');
});

// ----------------------------------------------------------------- buang latar

it('membuang latar bila gambar ditangkap atas latar rata', function () {
    $cutout = (new GdRemover)->cutout(produkAtasLatarRata());

    expect($cutout)->not->toBeNull();

    $im = imagecreatefrompng(Storage::disk('public')->path($cutout));

    // Penjuru jadi lut sinar; produk di tengah kekal pekat.
    expect((imagecolorat($im, 5, 5) >> 24) & 0x7F)->toBe(127)
        ->and((imagecolorat($im, 300, 300) >> 24) & 0x7F)->toBe(0);
});

it('mengalah pada latar sibuk dan bukan memulangkan hasil bercalar', function () {
    // Isian banjir atas latar sibuk tidak gagal dengan bersih — ia mengeluarkan
    // gambar berlubang yang nampak lebih teruk daripada gambar asal. Lebih baik
    // tidak menyentuhnya langsung.
    expect((new GdRemover)->cutout(produkAtasLatarSibuk()))->toBeNull();
});

it('membuang latar berwarna, bukan putih sahaja', function () {
    expect((new GdRemover)->cutout(produkAtasLatarRata(0x2E7D32)))->not->toBeNull();
});

// -------------------------------------------------------------------- latar AI

it('prompt AI melarang teks, logo dan papan tanda', function () {
    // Peraturan mutlak #7. Model imej tidak boleh dipercayai mengeja Melayu,
    // jadi kita tidak benarkan ia mencuba.
    $prompt = app(AiDriver::class)->prompt('kafe', 'kedai kopi');

    expect(strtolower($prompt))
        ->toContain('no text')
        ->toContain('no logos')
        ->toContain('no signage')
        ->toContain('cafe counter');
});

it('jatuh ke latar stock bila pembekal AI gagal', function () {
    config()->set('dynoads.poster.background.endpoint', 'https://contoh.test/jana');
    config()->set('dynoads.poster.background.api_key', 'kunci-ujian');
    Http::fake(['*' => Http::response([], 500)]);

    $path = app(AiDriver::class)->make('kafe');

    // Peniaga tetap dapat latar. Pembekal down bukan alasan untuk gagal.
    expect($path)->toContain('stock-kafe')
        ->and(Storage::disk('public')->exists($path))->toBeTrue();
});

it('guna stock bila tiada pembekal AI dikonfigur', function () {
    config()->set('dynoads.poster.background.endpoint', null);
    Http::preventStrayRequests();

    expect(app(AiDriver::class)->make('studio'))->toContain('stock-studio');
});

it('guna semula latar AI yang sama untuk mood dan industri yang sama', function () {
    config()->set('dynoads.poster.background.endpoint', 'https://contoh.test/jana');
    config()->set('dynoads.poster.background.api_key', 'kunci-ujian');
    Http::fake(['*' => Http::response(base64_encode('imej-palsu'), 200, ['Content-Type' => 'image/png'])]);

    $ai = app(AiDriver::class);
    $satu = $ai->make('kafe', 'kedai kopi');
    $dua = $ai->make('kafe', 'kedai kopi');

    expect($dua)->toBe($satu);
    Http::assertSentCount(1);
});

it('latar stock 1080x1080', function () {
    $path = (new StockDriver)->make('kedai');

    expect(getimagesize(Storage::disk('public')->path($path)))->toMatchArray([0 => 1080, 1 => 1080]);
});

// -------------------------------------------------------------------- auto-fit

it('mengecilkan font supaya teks panjang tetap muat, tidak pernah terpotong', function () {
    $pendek = fit('Promo hebat', 34, 1080, 0.078, 0.042);
    $panjang = fit(str_repeat('Kira duit lambat waktu peak hour ', 4), 34, 1080, 0.078, 0.042);

    expect($pendek)->toBe(84)
        ->and($panjang)->toBeLessThan($pendek)
        // Tidak pernah lebih kecil daripada had yang masih boleh dibaca atas telefon.
        ->and($panjang)->toBeGreaterThanOrEqual((int) round(1080 * 0.042));
});

it('teks kosong tidak memecahkan pengiraan saiz font', function () {
    expect(fit('', 34, 1080, 0.078, 0.042))->toBe(84)
        ->and(fit(null, 34, 1080, 0.078, 0.042))->toBe(84);
});

// ----------------------------------------------------------------------- cache

it('input sama menghasilkan cache_key sama', function () {
    $svc = app(PosterService::class);
    $data = ['headline' => 'Promo', 'price' => 'RM99'];

    expect($svc->cacheKey('promo-meletup', $data, 'stock', 'kafe', null))
        ->toBe($svc->cacheKey('promo-meletup', array_reverse($data, true), 'stock', 'kafe', null));
});

it('teks berbeza menghasilkan cache_key berbeza', function () {
    $svc = app(PosterService::class);

    expect($svc->cacheKey('promo-meletup', ['headline' => 'A'], 'stock', 'kafe', null))
        ->not->toBe($svc->cacheKey('promo-meletup', ['headline' => 'B'], 'stock', 'kafe', null));
});

it('cache_key unik dikuatkuasakan pada peringkat pangkalan data, per peniaga', function () {
    // Sejak Fasa 7a kuncinya (user_id, cache_key), bukan cache_key sahaja.
    // Dua peniaga berlainan boleh menghasilkan poster serupa; itu bukan
    // perlanggaran, dan dahulu ia menyebabkan render peniaga kedua gagal.
    $ali = masukSebagaiPemilik();

    PosterJob::create(['template' => 'promo-meletup', 'data' => [], 'cache_key' => 'sama']);

    expect(fn () => PosterJob::create(['template' => 'promo-meletup', 'data' => [], 'cache_key' => 'sama']))
        ->toThrow(UniqueConstraintViolationException::class);

    // Peniaga lain, kunci yang sama — dibenarkan.
    $siti = pemilik();

    // user_id sengaja bukan fillable — ia datang dari user yang log masuk,
    // tidak pernah dari input. Jadi di sini ia ditetapkan secara eksplisit.
    $milikSiti = new PosterJob(['template' => 'promo-meletup', 'data' => [], 'cache_key' => 'sama']);
    $milikSiti->user_id = $siti->id;
    $milikSiti->save();

    expect($milikSiti->exists)->toBeTrue()
        ->and($milikSiti->user_id)->toBe($siti->id)
        ->and($ali->id)->not->toBe($siti->id);
});

// ------------------------------------------------- poster terus jadi creative

/** Poster siap render, tanpa memanggil Playwright. */
function posterSiap(int $n = 1): PosterJob
{
    $path = "posters/ujian-{$n}.png";
    $im = imagecreatetruecolor(1080, 1080);
    imagefill($im, 0, 0, imagecolorallocate($im, 30 * $n, 90, 200));
    ob_start();
    imagepng($im);
    Storage::disk('public')->put($path, ob_get_clean());
    imagedestroy($im);

    return PosterJob::create([
        'template' => 'promo-meletup',
        'data' => ['headline' => "Poster {$n}"],
        'cache_key' => "ujian-{$n}",
        'output_path' => $path,
        'status' => 'done',
    ]);
}

it('bakul mengekalkan turutan peniaga menambah poster', function () {
    $basket = app(PosterBasket::class);
    [$a, $b, $c] = [posterSiap(1), posterSiap(2), posterSiap(3)];

    $basket->add($c->id);
    $basket->add($a->id);
    $basket->add($b->id);

    expect($basket->jobs()->pluck('id')->all())->toBe([$c->id, $a->id, $b->id]);
});

it('bakul tidak menerima poster yang sama dua kali', function () {
    $basket = app(PosterBasket::class);
    $job = posterSiap();

    $basket->add($job->id);
    $basket->add($job->id);

    expect($basket->count())->toBe(1);
});

it('bakul berhenti pada had creative', function () {
    $basket = app(PosterBasket::class);

    foreach (range(1, 6) as $n) {
        $basket->add(posterSiap($n)->id);
    }

    expect($basket->count())->toBe((int) config('dynoads.creative.max_images'));
});

it('poster dalam bakul jadi creative iklan tanpa muat turun', function () {
    Http::fake(['*' => Http::response(['data' => []])]);

    $basket = app(PosterBasket::class);
    $basket->add(posterSiap(1)->id);
    $basket->add(posterSiap(2)->id);

    Livewire::test(Create::class)
        ->set('problem', 'kira duit lambat waktu peak hour')
        ->set('offer', 'sistem POS fullset, pasang di kedai')
        ->set('phone', '60187922844')
        ->call('save')
        ->assertHasNoErrors();

    $set = AdSet::firstOrFail();

    expect($set->variants)->toHaveCount(2)
        ->and($set->variants->pluck('source_type')->all())->toBe(['poster', 'poster'])
        ->and($set->variants->pluck('poster_job_id')->filter())->toHaveCount(2);

    // Disalin, bukan dirujuk — poster boleh dijana semula atau dipadam kemudian,
    // tetapi creative iklan mesti kekal seperti masa ia dilancarkan.
    $imej = $set->variants->first()->image_path;

    expect($imej)->toContain("dynoads/{$set->id}/")
        ->and(Storage::disk('public')->exists($imej))->toBeTrue()
        ->and(getimagesize(Storage::disk('public')->path($imej)))->toMatchArray([0 => 1080, 1 => 1080]);
});

it('bakul dikosongkan selepas set iklan dibuat', function () {
    Http::fake(['*' => Http::response(['data' => []])]);

    $basket = app(PosterBasket::class);
    $basket->add(posterSiap()->id);

    Livewire::test(Create::class)
        ->set('problem', 'kira duit lambat waktu peak hour')
        ->set('offer', 'sistem POS fullset, pasang di kedai')
        ->set('phone', '60187922844')
        ->call('save')
        ->assertHasNoErrors();

    expect($basket->count())->toBe(0);
});

it('poster dan gambar upload boleh bercampur, poster didahulukan', function () {
    Http::fake(['*' => Http::response(['data' => []])]);

    app(PosterBasket::class)->add(posterSiap()->id);

    Livewire::test(Create::class)
        ->set('upload', [UploadedFile::fake()->image('gambar.jpg')])
        ->set('problem', 'kira duit lambat waktu peak hour')
        ->set('offer', 'sistem POS fullset, pasang di kedai')
        ->set('phone', '60187922844')
        ->call('save')
        ->assertHasNoErrors();

    expect(AdSet::firstOrFail()->variants->pluck('source_type')->all())
        ->toBe(['poster', 'upload']);
});

it('menolak bila gabungan poster dan gambar melebihi had', function () {
    $basket = app(PosterBasket::class);
    foreach (range(1, 3) as $n) {
        $basket->add(posterSiap($n)->id);
    }

    Livewire::test(Create::class)
        ->set('images', collect(range(1, 3))->map(fn ($i) => UploadedFile::fake()->image("{$i}.jpg"))->all())
        ->set('problem', 'kira duit lambat waktu peak hour')
        ->set('offer', 'sistem POS fullset, pasang di kedai')
        ->set('phone', '60187922844')
        ->call('save')
        ->assertHasErrors('images');

    expect(AdSet::count())->toBe(0);
});

it('menolak bila tiada poster mahupun gambar', function () {
    Livewire::test(Create::class)
        ->set('problem', 'kira duit lambat waktu peak hour')
        ->set('offer', 'sistem POS fullset, pasang di kedai')
        ->set('phone', '60187922844')
        ->call('save')
        ->assertHasErrors('images');

    expect(AdSet::count())->toBe(0);
});

// ---------------------------------------------------- serahan /buat → /poster

it('"Jadikan poster" membawa gambar ke skrin poster', function () {
    Http::fake(['*' => Http::response(['data' => []])]);

    Livewire::test(Create::class)
        ->set('upload', [UploadedFile::fake()->image('produk.jpg', 1200, 800)])
        ->assertCount('images', 1)
        ->call('makePoster', 0)
        ->assertRedirect(route('posters.create'))
        // Dikeluarkan dari senarai gambar biasa: satu gambar tidak boleh jadi
        // dua creative (yang mentah dan posternya).
        ->assertCount('images', 0);

    $handoff = app(ProductHandoff::class);

    expect($handoff->path())->not->toBeNull()
        ->and(getimagesize($handoff->absolutePath()))->toMatchArray([0 => 1080, 1 => 1080]);
});

it('skrin poster mengambil gambar yang dibawa', function () {
    Http::fake(['*' => Http::response(['data' => []])]);
    $handoff = app(ProductHandoff::class);

    Livewire::test(Create::class)
        ->set('upload', [UploadedFile::fake()->image('produk.jpg')])
        ->call('makePoster', 0);

    Livewire::test(App\Livewire\Posters\Create::class)
        ->assertSet('carried', $handoff->path())
        ->assertSee('Dibawa dari borang iklan');
});

it('gambar yang dibawa dilepaskan selepas poster masuk senarai iklan', function () {
    $handoff = app(ProductHandoff::class);

    Livewire::test(Create::class)
        ->set('upload', [UploadedFile::fake()->image('produk.jpg')])
        ->call('makePoster', 0);

    expect($handoff->path())->not->toBeNull();

    Livewire::test(App\Livewire\Posters\Create::class)
        ->set('posterJobId', posterSiap()->id)
        ->call('useForAd');

    // Kalau tidak, gambar sumber muncul semula pada poster seterusnya.
    expect($handoff->path())->toBeNull();
});

it('peniaga boleh membuang gambar yang dibawa', function () {
    $handoff = app(ProductHandoff::class);

    Livewire::test(Create::class)
        ->set('upload', [UploadedFile::fake()->image('produk.jpg')])
        ->call('makePoster', 0);

    Livewire::test(App\Livewire\Posters\Create::class)
        ->call('clearProduct')
        ->assertSet('carried', null);

    expect($handoff->path())->toBeNull();
});

// -------------------------------------------------------- had masa & ralat

it('render tidak pernah dibenarkan berjalan melebihi had masa PHP', function () {
    // Kalau ia melebihi, PHP membunuh permintaan dahulu dengan 500 mentah —
    // Livewire memaparkan halaman ralat penuh dalam iframe dan peniaga nampak
    // kotak hitam, bukan ayat yang menerangkan apa yang berlaku.
    $timeout = new ReflectionMethod(PosterService::class, 'renderTimeout');

    config()->set('dynoads.poster.render_timeout', 90);
    $service = app(PosterService::class);

    $phpLimit = (float) ini_get('max_execution_time');
    $actual = $timeout->invoke($service);

    if ($phpLimit > 0) {
        expect($actual)->toBeLessThan($phpLimit);
    } else {
        expect($actual)->toBe(90.0);
    }
});

it('menterjemah output perender kepada ayat yang boleh diikut', function () {
    $poster = app(PosterService::class);

    expect($poster->explain("browserType.launch: Executable doesn't exist at /opt/x/chrome"))
        ->toContain('Chrome atau Chromium tiada')
        ->toContain('google-chrome-stable')
        ->and($poster->explain("Error: Cannot find module 'playwright'"))
        ->toContain('npm ci')
        ->and($poster->explain(''))
        ->toContain('Tiada sebab dilaporkan');
});

it('memendekkan longgokan stack trace, bukan membuangnya ke skrin', function () {
    $panjang = str_repeat('Ralat rawak yang panjang. ', 100);

    expect(mb_strlen(app(PosterService::class)->explain($panjang)))
        ->toBeLessThanOrEqual(303);
});

it('skrip render yang hilang gagal serta-merta, bukan menunggu timeout', function () {
    config()->set('dynoads.poster.renderer', '/laluan/yang/tiada.mjs');

    expect(fn () => app(PosterService::class)->render(
        'promo-meletup', ['headline' => 'Ujian'],
    ))->toThrow(RuntimeException::class, 'Skrip render poster tiada');
});
