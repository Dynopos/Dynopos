<?php

use App\Services\Caption\CaptionWriter;
use App\Services\Caption\ClaudeWriter;
use App\Services\Caption\OpenAiWriter;
use App\Services\CaptionService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/** Balasan OpenAI dengan teks yang diberi. */
function balasanOpenAi(string $text): array
{
    return ['choices' => [['message' => ['content' => $text]]]];
}

/** Balasan Claude dengan teks yang diberi. */
function balasanClaude(string $text): array
{
    return ['content' => [['type' => 'text', 'text' => $text]]];
}

beforeEach(function () {
    config()->set('dynoads.caption.driver', 'openai');
    config()->set('dynoads.caption.openai.api_key', 'sk-ujian');
});

// ------------------------------------------------------------------- OpenAI

it('memparse JSON caption dari balasan OpenAI', function () {
    Http::fake(['*/v1/chat/completions' => Http::response(balasanOpenAi('["Caption satu.", "Caption dua."]'))]);

    $set = app(CaptionService::class)->generate(bisnes(), 'kira duit lambat', 'sistem POS', 2);

    expect($set->captions)->toBe(['Caption satu.', 'Caption dua.'])
        ->and($set->fromFallback)->toBeFalse();
});

it('menghantar prompt sistem dan pengguna sebagai mesej berasingan', function () {
    Http::fake(['*/v1/chat/completions' => Http::response(balasanOpenAi('["Satu."]'))]);

    app(CaptionService::class)->generate(bisnes(), 'kira duit lambat', 'sistem POS fullset', 1);

    Http::assertSent(function (Request $r) {
        return $r['model'] === config('dynoads.caption.openai.model')
            && $r['messages'][0]['role'] === 'system'
            && str_contains($r['messages'][0]['content'], 'AI Nurin')
            && $r['messages'][1]['role'] === 'user'
            && str_contains($r['messages'][1]['content'], 'kira duit lambat')
            // max_tokens ditolak oleh model OpenAI yang lebih baharu.
            && isset($r['max_completion_tokens'])
            && ! isset($r['max_tokens']);
    });
});

it('guna Bearer token untuk OpenAI', function () {
    Http::fake(['*/v1/chat/completions' => Http::response(balasanOpenAi('["Satu."]'))]);

    app(CaptionService::class)->generate(bisnes(), 'masalah', 'tawaran', 1);

    Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer sk-ujian'));
});

// ------------------------------------------------------------------- Claude

it('boleh bertukar ke Claude tanpa mengubah kod', function () {
    config()->set('dynoads.caption.driver', 'claude');
    config()->set('dynoads.caption.claude.api_key', 'sk-ant-ujian');

    Http::fake(['*/v1/messages' => Http::response(balasanClaude('["Caption satu."]'))]);

    $set = app(CaptionService::class)->generate(bisnes(), 'masalah', 'tawaran', 1);

    expect($set->captions)->toBe(['Caption satu.'])
        ->and($set->fromFallback)->toBeFalse();

    Http::assertSent(fn (Request $r) => $r->hasHeader('x-api-key', 'sk-ant-ujian')
        && str_contains($r->url(), '/v1/messages'));
});

it('kedua-dua pembekal menerima prompt yang sama', function () {
    // Prompt duduk dalam CaptionService, bukan dalam writer — jadi menukar
    // pembekal tidak menukar kualiti copy.
    $reflection = new ReflectionMethod(CaptionService::class, 'systemPrompt');
    $prompt = $reflection->invoke(app(CaptionService::class));

    expect($prompt)->toContain('AI Nurin')
        ->toContain('Bahasa Melayu santai')
        ->toContain('Tekan WhatsApp untuk info lanjut');
});

// ------------------------------------------------------------------ umum

it('mengeluarkan JSON walaupun model bungkus dengan teks lain', function () {
    Http::fake(['*/v1/chat/completions' => Http::response(
        balasanOpenAi("Ini caption anda:\n```json\n[\"Satu.\"]\n```")
    )]);

    expect(app(CaptionService::class)->generate(bisnes(), 'masalah', 'tawaran', 1)->captions)->toBe(['Satu.']);
});

it('cuba semula bila balasan pertama bukan JSON', function () {
    Http::fakeSequence()
        ->push(balasanOpenAi('maaf saya tak faham'))
        ->push(balasanOpenAi('["Caption baik."]'));

    expect(app(CaptionService::class)->generate(bisnes(), 'masalah', 'tawaran', 1)->captions)->toBe(['Caption baik.']);

    Http::assertSentCount(2);
});

it('jatuh ke caption asas bila pembekal gagal — peniaga tidak tersekat', function () {
    Http::fake(['*' => Http::response([], 500)]);

    $set = app(CaptionService::class)->generate(bisnes(), 'kira duit lambat', 'sistem POS fullset', 2);

    expect($set->captions)->toHaveCount(2)
        ->and($set->fromFallback)->toBeTrue()
        ->and($set->reason)->toContain('OpenAI')
        ->and($set->captions[0])->toContain('Tekan WhatsApp untuk info lanjut');
});

it('menyebut kunci yang belum diisi, dan kunci pembekal yang betul', function () {
    config()->set('dynoads.caption.openai.api_key', '');
    Http::fake(['*' => Http::response([], 401)]);

    $set = app(CaptionService::class)->generate(bisnes(), 'masalah', 'tawaran', 1);

    expect($set->reason)->toContain('OPENAI_API_KEY')
        ->and($set->reason)->not->toContain('ANTHROPIC');
});

it('menyebut ANTHROPIC_API_KEY bila pemandu Claude dipilih', function () {
    config()->set('dynoads.caption.driver', 'claude');
    config()->set('dynoads.caption.claude.api_key', '');
    Http::fake(['*' => Http::response([], 401)]);

    expect(app(CaptionService::class)->generate(bisnes(), 'masalah', 'tawaran', 1)->reason)
        ->toContain('ANTHROPIC_API_KEY');
});

it('membuang perkataan larangan dari caption', function () {
    Http::fake(['*/v1/chat/completions' => Http::response(
        balasanOpenAi('["Sistem POS terbaik di Malaysia, dijamin untung."]')
    )]);

    $caption = app(CaptionService::class)->generate(bisnes(), 'masalah', 'tawaran', 1)->captions[0];

    expect(mb_strtolower($caption))->not->toContain('terbaik di malaysia')
        ->and(mb_strtolower($caption))->not->toContain('dijamin');
});

it('mengesan perkataan larangan dalam caption yang diedit sendiri', function () {
    $service = app(CaptionService::class);

    expect($service->hasForbiddenWord('Hasil dijamin dalam 7 hari'))->toBeTrue()
        ->and($service->hasForbiddenWord('Tekan WhatsApp untuk info lanjut'))->toBeFalse()
        ->and($service->hasForbiddenWord('Nak tengok demo?'))->toBeTrue();
});

it('menandakan dakwaan superlatif yang Meta biasa tolak', function () {
    $service = app(CaptionService::class);

    expect($service->riskyClaims('POS system termurah di Malaysia'))->toBe(['termurah'])
        ->and($service->riskyClaims('Paling murah dan no.1 di pasaran'))
        ->toBe(['paling murah', 'nombor satu'])
        ->and($service->riskyClaims('Full set RM1350 tanpa bayaran bulanan'))->toBe([]);
});

it('dakwaan berisiko hanya diberi amaran, bukan menghalang', function () {
    expect(app(CaptionService::class)->hasForbiddenWord('POS termurah di Malaysia'))->toBeFalse();
});

it('pemandu tidak dikenali jatuh ke OpenAI, bukan meletup', function () {
    config()->set('dynoads.caption.driver', 'entah-apa');

    expect(app(CaptionWriter::class))->toBeInstanceOf(OpenAiWriter::class);
});

it('pemandu claude memberikan ClaudeWriter', function () {
    config()->set('dynoads.caption.driver', 'claude');

    expect(app(CaptionWriter::class))->toBeInstanceOf(ClaudeWriter::class);
});
