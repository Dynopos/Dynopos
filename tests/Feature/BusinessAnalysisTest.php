<?php

use App\Exceptions\BusinessNotConfirmed;
use App\Livewire\AdSets\Create;
use App\Livewire\AdSets\Review;
use App\Livewire\Businesses\Confirm;
use App\Models\AdSet;
use App\Models\AdVariant;
use App\Models\Business;
use App\Models\User;
use App\Services\BusinessAnalyst;
use App\Services\CaptionService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Peraturan mutlak #14 — analisis bisnes disahkan dahulu.
 *
 * Bob pernah demo di depan pelanggan sewa skylift, dan app menulis caption
 * tentang oksigen. AI hanya nampak masalah dan tawaran, lalu meneka bidangnya.
 * Test di sini memastikan tekaan itu tidak boleh sampai ke iklan.
 */

/** Balasan OpenAI dengan teks yang diberi. */
function balasan(string $text): array
{
    return ['choices' => [['message' => ['content' => $text]]]];
}

function analisisSkylift(array $override = []): string
{
    return json_encode([
        'field' => 'Sewa skylift dan lori kren untuk kerja tempat tinggi',
        'summary' => 'Sewa skylift dengan operator untuk pasang papan tanda, cat bangunan dan potong pokok.',
        'offers' => ['sewa skylift 14 meter', 'operator berlesen'],
        'customers' => ['kontraktor', 'kedai papan tanda'],
        'pains' => ['tangga tak cukup tinggi'],
        'angles' => ['kerja siap sehari'],
        'avoid' => ['oksigen', 'tangki gas', 'lif bangunan'],
        'confidence' => 'tinggi',
        'question' => '',
        ...$override,
    ]);
}

function skylift(array $attributes = []): Business
{
    return Business::factory()->create([
        'name' => 'Ali Skylift',
        'description' => 'Sewa skylift untuk kerja tempat tinggi di Kelantan.',
        'analysis' => json_decode(analisisSkylift(), true),
        ...$attributes,
    ]);
}

beforeEach(function () {
    // Tiada panggilan sebenar ke Meta atau pembekal AI. /buat cuba menarik
    // senarai negeri semasa mount; tanpa fake ia gagal dengan selamat.
    Http::preventStrayRequests();
    Storage::fake('public');
    config()->set('dynoads.caption.driver', 'openai');
    config()->set('dynoads.caption.openai.api_key', 'sk-ujian');
});

// ------------------------------------------------------------ caption

it('tidak menulis caption untuk bisnes yang belum disahkan', function () {
    Http::fake();

    expect(fn () => app(CaptionService::class)->generate(
        skylift(['analysis_confirmed_at' => null]), 'kerja tinggi', 'sewa skylift', 1
    ))->toThrow(BusinessNotConfirmed::class);

    Http::assertNothingSent();
});

it('menghantar profil bisnes yang disahkan dalam prompt caption', function () {
    Http::fake(['*/v1/chat/completions' => Http::response(balasan('["Tangga tak sampai? Skylift sampai."]'))]);

    app(CaptionService::class)->generate(skylift(), 'kerja tinggi', 'sewa skylift', 1);

    Http::assertSent(fn (Request $r) => str_contains($r['messages'][0]['content'], 'KENAL BISNES DULU')
        && str_contains($r['messages'][1]['content'], 'Bidang: Sewa skylift dan lori kren')
        && str_contains($r['messages'][1]['content'], 'JANGAN sebut (bukan bisnes ini): oksigen'));
});

it('membuang caption oksigen untuk bisnes skylift dan cuba semula', function () {
    Http::fakeSequence()
        ->push(balasan('["Bekalan oksigen untuk klinik anda."]'))
        ->push(balasan('["Tangga tak sampai tingkat tiga? Skylift kami sampai."]'));

    $set = app(CaptionService::class)->generate(skylift(), 'kerja tinggi', 'sewa skylift', 1);

    expect($set->captions)->toBe(['Tangga tak sampai tingkat tiga? Skylift kami sampai.'])
        ->and($set->fromFallback)->toBeFalse();

    Http::assertSentCount(2);
});

it('perkataan "jangan sebut" dipadan ikut perkataan penuh', function () {
    $business = bisnes(); // avoid: "pos laju"

    expect($business->offFieldTerms('Sistem POS untuk kedai runcit'))->toBe([])
        ->and($business->offFieldTerms('Hantar barang guna Pos Laju'))->toBe(['pos laju']);
});

// ------------------------------------------------------------ analisis

it('menganalisis bisnes, menyimpan hasil dan menunggu pengesahan', function () {
    Http::fake(['*/v1/chat/completions' => Http::response(balasan("Ini analisis:\n".analisisSkylift()))]);

    $business = Business::factory()->unanalysed()->create(['name' => 'Ali Skylift']);

    app(BusinessAnalyst::class)->analyse($business, null, "Masalah pelanggan: kerja tinggi\nTawaran: sewa skylift");

    $business->refresh();

    expect($business->field())->toBe('Sewa skylift dan lori kren untuk kerja tempat tinggi')
        ->and($business->avoidList())->toContain('oksigen')
        ->and($business->analysis['source'])->toBe('ai')
        ->and($business->analysis_count)->toBe(1)
        ->and($business->hasConfirmedAnalysis())->toBeFalse();

    Http::assertSent(fn (Request $r) => str_contains($r['messages'][0]['content'], 'AI Amir')
        && str_contains($r['messages'][1]['content'], 'Tawaran: sewa skylift'));
});

it('analisis semula membuang pengesahan lama dan menghantar pembetulan', function () {
    Http::fake(['*/v1/chat/completions' => Http::response(balasan(analisisSkylift(['avoid' => ['ski']])))]);

    $business = skylift(['analysis' => ['field' => 'Jual gas oksigen', 'avoid' => ['minyak']]]);

    app(BusinessAnalyst::class)->analyse($business, 'Kami sewa skylift, bukan jual gas.');

    expect($business->refresh()->hasConfirmedAnalysis())->toBeFalse()
        // "Jangan sebut" lama kekal; analisis baharu menambah, bukan menggantikan.
        ->and($business->avoidList())->toBe(['minyak', 'ski']);

    Http::assertSent(fn (Request $r) => str_contains($r['messages'][1]['content'], 'PEMBETULAN DARI PENIAGA')
        && str_contains($r['messages'][1]['content'], 'bukan jual gas'));
});

it('bila AI gagal, analisis disusun dari tulisan peniaga untuk disemak', function () {
    Http::fake(['*' => Http::response([], 500)]);

    $business = Business::factory()->unanalysed()->create([
        'name' => 'Ali Skylift',
        'description' => 'Sewa skylift untuk kerja tempat tinggi.',
    ]);

    app(BusinessAnalyst::class)->analyse($business);

    expect($business->refresh()->analysis['source'])->toBe('manual')
        ->and($business->field())->toBe('Ali Skylift')
        ->and($business->analysis['reason'])->toContain('OpenAI')
        ->and($business->hasConfirmedAnalysis())->toBeFalse();
});

it('analisis semula dihadkan supaya tiada loop kos', function () {
    config()->set('dynoads.analysis.max_per_business', 2);
    Http::fake();

    $business = skylift(['analysis_count' => 2]);

    expect(fn () => app(BusinessAnalyst::class)->analyse($business, 'betulkan'))
        ->toThrow(RuntimeException::class, 'Had analisis');

    Http::assertNothingSent();
});

it('pengesahan menyimpan pembetulan peniaga sendiri', function () {
    $business = skylift(['analysis_confirmed_at' => null]);

    app(BusinessAnalyst::class)->confirm($business, [
        'field' => 'Sewa skylift',
        'avoid' => "oksigen, gas\nlif",
    ]);

    expect($business->refresh()->hasConfirmedAnalysis())->toBeTrue()
        ->and($business->field())->toBe('Sewa skylift')
        ->and($business->avoidList())->toBe(['oksigen', 'gas', 'lif']);
});

// ------------------------------------------------------------ skrin

it('/buat dengan bisnes baharu menghantar peniaga ke skrin sahkan bisnes', function () {
    masukSebagaiPemilik();

    $component = Livewire::test(Create::class)
        ->set('upload', [UploadedFile::fake()->image('a.jpg', 1200, 1200)])
        ->set('businessName', 'Ali Skylift')
        ->set('businessDescription', 'Sewa skylift untuk kerja tempat tinggi di Kelantan.')
        ->set('problem', 'tangga tak cukup tinggi')
        ->set('offer', 'sewa skylift dengan operator')
        ->set('phone', '60123456789')
        ->call('save')
        ->assertHasNoErrors();

    $business = Business::firstOrFail();
    $set = AdSet::firstOrFail();

    $component->assertRedirect(route('businesses.confirm', ['business' => $business, 'set' => $set->id]));

    expect($set->business_id)->toBe($business->id)
        ->and($business->user_id)->toBe(auth()->id());
});

it('/buat menolak bisnes baharu tanpa penerangan', function () {
    masukSebagaiPemilik();

    Livewire::test(Create::class)
        ->set('upload', [UploadedFile::fake()->image('a.jpg', 1200, 1200)])
        ->set('businessName', 'Ali Skylift')
        ->set('problem', 'tangga tak cukup tinggi')
        ->set('offer', 'sewa skylift dengan operator')
        ->set('phone', '60123456789')
        ->call('save')
        ->assertHasErrors(['businessDescription']);

    expect(AdSet::count())->toBe(0);
});

it('skrin sahkan menganalisis, dan sahkan membawa ke caption', function () {
    masukSebagaiPemilik();
    Http::fake(['*/v1/chat/completions' => Http::response(balasan(analisisSkylift()))]);

    $business = Business::factory()->unanalysed()->create(['name' => 'Ali Skylift']);
    $set = AdSet::factory()->create(['business_id' => $business->id]);

    Livewire::withQueryParams(['set' => $set->id])
        ->test(Confirm::class, ['business' => $business])
        ->assertSet('field', 'Sewa skylift dan lori kren untuk kerja tempat tinggi')
        ->assertSee('Betul ke ni bisnes anda?')
        ->set('avoid', 'oksigen, tangki gas, hospital')
        ->call('confirm')
        ->assertRedirect(route('ad-sets.review', $set));

    expect($business->refresh()->hasConfirmedAnalysis())->toBeTrue()
        ->and($business->avoidList())->toBe(['oksigen', 'tangki gas', 'hospital']);
});

it('/semak tidak menulis caption sebelum bisnes disahkan', function () {
    Http::fake();

    $set = AdSet::factory()->create(['business_id' => skylift(['analysis_confirmed_at' => null])->id]);
    AdVariant::factory()->for($set, 'adSet')->create(['caption' => null]);

    Livewire::test(Review::class, ['adSet' => $set])
        ->assertRedirect(route('businesses.confirm', ['business' => $set->business_id, 'set' => $set->id]));

    Http::assertNothingSent();
});

it('/semak menolak caption yang sebut perkara "bukan bisnes ini"', function () {
    $set = AdSet::factory()->create(['business_id' => skylift()->id]);
    $variant = AdVariant::factory()->for($set, 'adSet')->create();

    Livewire::test(Review::class, ['adSet' => $set])
        ->set("captions.{$variant->id}", 'Bekalan oksigen untuk klinik anda.')
        ->call('approve')
        ->assertSet('error', 'Caption iklan #'.$variant->position.' sebut "oksigen" — itu bukan bisnes anda. Ubah dulu.');

    expect($variant->refresh()->meta_campaign_id)->toBeNull();
});

it('set iklan lama tanpa bisnes tidak memanggil AI, dan boleh dipautkan', function () {
    Http::fake();

    $business = skylift();
    $set = AdSet::factory()->create(['business_id' => null]);
    AdVariant::factory()->for($set, 'adSet')->create(['caption' => null]);

    Livewire::test(Review::class, ['adSet' => $set])
        ->assertSee('belum ada profil bisnes')
        ->call('attachBusiness', $business->id)
        ->assertSet('error', null);

    expect($set->refresh()->business_id)->toBe($business->id);
    Http::assertNothingSent();
});

// ------------------------------------------------------------ pengasingan

it('peniaga tidak boleh membuka atau memilih bisnes peniaga lain', function () {
    $ali = User::factory()->owner()->create();
    $siti = User::factory()->owner()->create();

    $this->actingAs($ali);
    $milikAli = skylift();

    $this->actingAs($siti);

    $this->get(route('businesses.confirm', $milikAli))->assertNotFound();

    Livewire::test(Create::class)
        ->set('upload', [UploadedFile::fake()->image('a.jpg', 1200, 1200)])
        ->set('businessId', $milikAli->id)
        ->set('problem', 'tangga tak cukup tinggi')
        ->set('offer', 'sewa skylift dengan operator')
        ->set('phone', '60123456789')
        ->call('save')
        ->assertHasErrors(['businessId']);

    expect(Business::pluck('id')->all())->toBe([])
        ->and(AdSet::withoutGlobalScope('user')->count())->toBe(0);
});
