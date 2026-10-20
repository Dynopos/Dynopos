<?php

namespace App\Services;

use App\Models\Business;
use App\Services\Caption\CaptionWriter;
use RuntimeException;

/**
 * Analisis bisnes sebelum caption.
 *
 * AI membaca apa yang peniaga tulis tentang bisnesnya, lalu menyatakan
 * semula: bidang, servis sebenar, pelanggan, sudut jualan, dan perkara yang
 * BUKAN bisnes ini. Peniaga mengesahkan atau membetulkan. CaptionService
 * enggan menulis untuk bisnes yang belum disahkan.
 *
 * Pembekal sama dengan caption (CaptionWriter). LLM hanya menulis teks — ia
 * tidak pernah memanggil Meta (peraturan mutlak #5).
 */
class BusinessAnalyst
{
    private const LISTS = ['offers', 'customers', 'pains', 'angles', 'avoid'];

    public function __construct(private CaptionWriter $writer) {}

    public function analysesLeft(Business $business): int
    {
        return max(0, (int) config('dynoads.analysis.max_per_business') - $business->analysis_count);
    }

    /**
     * Jana (atau jana semula) analisis. Pengesahan lama sentiasa dibuang —
     * analisis baharu mesti dilihat manusia dahulu.
     *
     * Bila AI tidak dapat dihubungi, analisis disusun dari tulisan peniaga
     * sendiri (source = manual) supaya dia boleh semak dan sahkan. Peniaga
     * tidak tersekat, dan tiada apa yang ditulis tanpa dia nampak.
     */
    public function analyse(Business $business, ?string $correction = null, ?string $hint = null): Business
    {
        if ($this->analysesLeft($business) === 0) {
            throw new RuntimeException('Had analisis semula untuk bisnes ini sudah habis. Betulkan medan di bawah sendiri.');
        }

        $attempts = (int) config('dynoads.caption.retries') + 1;
        $analysis = null;
        $reason = null;

        for ($i = 0; $i < $attempts && $analysis === null; $i++) {
            try {
                $analysis = $this->parse($this->writer->write(
                    $this->systemPrompt(),
                    $this->userPrompt($business, $correction, $hint),
                ));
            } catch (RuntimeException $e) {
                $reason = $e->getMessage();
            }
        }

        $analysis ??= $this->manual($business, $this->writer->configured()
            ? $this->writer->name().' tidak dapat dihubungi: '.($reason ?? 'sebab tidak diketahui')
            : $this->writer->keyName().' belum diisi.');

        // "Jangan sebut" yang peniaga tambah sendiri kekal selepas analisis semula.
        $analysis['avoid'] = $this->unique([...(array) ($business->analysis['avoid'] ?? []), ...$analysis['avoid']]);

        $business->forceFill([
            'analysis' => $analysis,
            'analysis_confirmed_at' => null,
            'analysis_count' => $business->analysis_count + 1,
        ])->save();

        return $business;
    }

    /**
     * Peniaga sahkan — dengan pembetulan kecilnya sendiri jika ada.
     *
     * @param  array{field?:string, summary?:string, avoid?:string}  $edits
     */
    public function confirm(Business $business, array $edits = []): Business
    {
        $analysis = (array) $business->analysis;

        foreach (['field', 'summary'] as $key) {
            if (filled($edits[$key] ?? null)) {
                $analysis[$key] = trim(mb_substr((string) $edits[$key], 0, 500));
            }
        }

        if (array_key_exists('avoid', $edits)) {
            $analysis['avoid'] = $this->unique(preg_split('/[,\n]+/', (string) $edits['avoid']));
        }

        if (blank($analysis['field'] ?? null)) {
            throw new RuntimeException('Isi bidang bisnes dulu.');
        }

        $business->forceFill([
            'analysis' => $analysis,
            'analysis_confirmed_at' => now(),
        ])->save();

        return $business;
    }

    /** @return array<string, mixed> */
    protected function parse(string $text): array
    {
        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $text = $m[0];
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded) || blank($decoded['field'] ?? null)) {
            throw new RuntimeException('Balasan analisis bukan JSON yang sah.');
        }

        $analysis = [
            'field' => $this->line($decoded['field'], 200),
            'summary' => $this->line($decoded['summary'] ?? '', 600),
            'confidence' => in_array($decoded['confidence'] ?? null, ['tinggi', 'sederhana', 'rendah'], true)
                ? $decoded['confidence'] : 'sederhana',
            'question' => $this->line($decoded['question'] ?? '', 300),
            'source' => 'ai',
        ];

        foreach (self::LISTS as $key) {
            $analysis[$key] = $this->unique(array_slice((array) ($decoded[$key] ?? []), 0, 8));
        }

        return $analysis;
    }

    /** @return array<string, mixed> */
    protected function manual(Business $business, string $reason): array
    {
        return [
            'field' => $this->line($business->name, 200),
            'summary' => $this->line($business->description, 600),
            'confidence' => 'rendah',
            'question' => '',
            'source' => 'manual',
            'reason' => $reason,
            ...array_fill_keys(self::LISTS, []),
        ];
    }

    private function line(mixed $value, int $max): string
    {
        return trim(mb_substr(preg_replace('/\s+/u', ' ', (string) (is_scalar($value) ? $value : '')), 0, $max));
    }

    /** @return array<int, string> */
    private function unique(array $items): array
    {
        return collect($items)
            ->map(fn ($item) => $this->line($item, 80))
            ->filter()
            ->unique(fn (string $item) => mb_strtolower($item))
            ->take(15)
            ->values()
            ->all();
    }

    protected function systemPrompt(): string
    {
        return <<<'TXT'
        Kau AI Amir, ketua projek iklan untuk peniaga kecil Malaysia. Sebelum
        AI Nurin tulis caption, kau mesti faham bisnes ini dengan betul.

        Baca apa yang peniaga tulis. Nyatakan semula bisnesnya dengan jelas
        supaya peniaga boleh kata "betul" atau betulkan.

        PERATURAN
        - Guna HANYA apa yang peniaga tulis. Jangan reka produk, harga,
          promosi atau lokasi yang tiada.
        - "field" ialah bidang sebenar dalam satu frasa pendek. Contoh:
          "Sewa skylift dan lori kren untuk kerja tempat tinggi".
        - "avoid" ialah perkara yang MUDAH TERSILAP dikaitkan dengan nama atau
          perkataan bisnes ini tetapi BUKAN bisnes ini. Contoh untuk sewa
          skylift: "oksigen", "tangki gas", "lif bangunan", "ski". Satu atau
          dua perkataan setiap satu. 3 hingga 8 item.
        - Kalau tulisan peniaga kabur, letak confidence "rendah" dan tulis satu
          soalan pendek dalam "question". Kalau jelas, "question" kosong.
        - Bahasa Melayu santai Malaysia.

        Balas JSON sahaja, tiada teks lain:
        {"field": "...", "summary": "2-3 ayat", "offers": ["..."],
         "customers": ["..."], "pains": ["..."], "angles": ["..."],
         "avoid": ["..."], "confidence": "tinggi|sederhana|rendah",
         "question": ""}
        TXT;
    }

    protected function userPrompt(Business $business, ?string $correction, ?string $hint): string
    {
        $text = "Nama bisnes: {$business->name}\nApa bisnes ini buat (tulisan peniaga): {$business->description}";

        if (filled($hint)) {
            $text .= "\n\nIklan yang peniaga sedang buat:\n{$hint}";
        }

        if (filled($business->analysis['field'] ?? null)) {
            $text .= "\n\nAnalisis sebelum ini:\nBidang: ".$business->field()."\nRingkasan: ".($business->analysis['summary'] ?? '');
        }

        if (filled($correction)) {
            $text .= "\n\nPEMBETULAN DARI PENIAGA (ikut ini, ia lebih tepat dari analisis sebelum ini):\n".trim($correction);
        }

        return $text;
    }
}
