<?php

namespace App\Services;

use App\Services\Caption\CaptionWriter;
use RuntimeException;

/**
 * Caption iklan. Semua panggilan LLM lalu di sini — tidak pernah terus dari
 * Livewire (peraturan CLAUDE.md).
 *
 * Pembekal (OpenAI, Claude) disuntik. Prompt, penghuraian dan caption ganti
 * kekal di sini, jadi menukar pembekal tidak menukar kualiti copy.
 *
 * LLM hanya menulis teks. Ia tidak pernah memanggil Meta (peraturan mutlak #5).
 */
class CaptionService
{
    public function __construct(private CaptionWriter $writer) {}

    /**
     * Jana beberapa caption berbeza — satu untuk setiap gambar,
     * supaya split test membandingkan sudut ayat, bukan hanya gambar.
     */
    public function generate(string $problem, string $offer, int $count = 1): CaptionSet
    {
        $count = max(1, min($count, (int) config('dynoads.creative.max_creatives')));
        $attempts = (int) config('dynoads.caption.retries') + 1;
        $last = null;

        for ($i = 0; $i < $attempts; $i++) {
            try {
                $captions = $this->ask($problem, $offer, $count);

                if (count($captions) >= $count) {
                    return CaptionSet::fromAi(array_slice($captions, 0, $count));
                }

                $last = new RuntimeException('Claude pulangkan caption tidak cukup.');
            } catch (RuntimeException $e) {
                $last = $e;
            }
        }

        // Jangan halang peniaga sebab AI gagal — bagi caption asas yang boleh
        // diedit, TAPI katakan ia berlaku. Fallback senyap membuatkan kunci API
        // yang tidak diisi kelihatan seperti AI yang menulis dengan teruk.
        return CaptionSet::fromFallback(
            $this->fallback($problem, $offer, $count),
            $this->writer->configured()
                ? $this->writer->name().' tidak dapat dihubungi: '.($last?->getMessage() ?? 'sebab tidak diketahui')
                : $this->writer->keyName().' belum diisi.',
        );
    }

    /** @return array<int, string> */
    protected function ask(string $problem, string $offer, int $count): array
    {
        return $this->parse($this->writer->write(
            $this->systemPrompt(),
            $this->userPrompt($problem, $offer, $count),
        ));
    }

    /** @return array<int, string> */
    protected function parse(string $text): array
    {
        // Model kadang bungkus JSON dalam fence. Ambil array pertama yang sah.
        if (preg_match('/\[[\s\S]*\]/', $text, $m)) {
            $text = $m[0];
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Balasan Claude bukan JSON yang sah.');
        }

        return collect($decoded)
            ->map(fn ($c) => is_array($c) ? (string) ($c['caption'] ?? '') : (string) $c)
            ->map(fn (string $c) => $this->clean($c))
            ->filter()
            ->values()
            ->all();
    }

    /** Buang ayat larangan Meta dan ruang berlebihan. */
    public function clean(string $caption): string
    {
        $caption = trim(preg_replace('/[ \t]+/', ' ', $caption));

        foreach ((array) config('dynoads.caption.forbidden_words') as $word) {
            $caption = preg_replace('/'.preg_quote($word, '/').'/iu', '', $caption);
        }

        return trim(preg_replace('/ +([,.!?])/', '$1', preg_replace('/[ \t]{2,}/', ' ', $caption)));
    }

    /**
     * Dakwaan yang tidak boleh dibuktikan.
     *
     * Ini AMARAN, bukan halangan. "Termurah di Malaysia" mungkin memang benar
     * untuk peniaga itu — tetapi Meta menilai dakwaan superlatif sebagai
     * berisiko, dan peniaga patut tahu sebelum membelanjakan duit, bukan
     * selepas iklan ditolak.
     *
     * @return array<int, string>
     */
    public function riskyClaims(string $caption): array
    {
        $patterns = [
            'termurah' => 'termurah',
            'paling murah' => 'paling murah',
            'terbaik' => 'terbaik',
            'paling laris' => 'paling laris',
            'no.1' => 'nombor satu',
            'nombor 1' => 'nombor satu',
            '100%' => '100%',
        ];

        $found = [];

        foreach ($patterns as $needle => $label) {
            if (mb_stripos($caption, $needle) !== false) {
                $found[$label] = $label;
            }
        }

        return array_values($found);
    }

    public function hasForbiddenWord(string $caption): bool
    {
        foreach ((array) config('dynoads.caption.forbidden_words') as $word) {
            if (mb_stripos($caption, $word) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function systemPrompt(): string
    {
        return <<<'TXT'
        Kau AI Nurin, penulis copy untuk peniaga kecil Malaysia. Kau tulis untuk
        orang yang scroll Facebook atas telefon sambil buat kerja lain.

        BENTUK
        - 3 hingga 5 baris pendek. Baris pertama mesti berhenti scroll.
        - Bahasa Melayu santai yang orang sebenar guna. Bukan BM baku sekolah,
          bukan bahasa korporat.
        - Satu idea satu baris. Jangan ayat panjang berbelit.
        - Akhiri dengan: Tekan WhatsApp untuk info lanjut

        BARIS PERTAMA
        Mula dengan detik yang peniaga kenal, bukan pengumuman.
        Bagus:  "Pukul 8 malam. Duit dalam laci tak sama dengan jualan."
        Teruk:  "Kami menawarkan sistem POS yang berkualiti."
        Guna nombor dan waktu yang khusus bila boleh. Yang khusus lebih dipercayai.

        SETIAP CAPTION SUDUT BERBEZA
        Kalau diminta 3 caption, jangan bagi tiga versi ayat yang sama.
        Sudut yang boleh dipakai: kos tersembunyi hari ini · apa berubah selepas
        pasang · bantahan yang orang selalu ada · siapa ia sesuai · apa yang
        berlaku kalau biarkan.

        JANGAN
        - Jangan janji hasil atau pulangan.
        - Jangan guna "dijamin", "terbaik di Malaysia", "nombor 1", "demo".
        - Jangan tulis dakwaan yang tak boleh dibuktikan ("termurah di Malaysia").
          Kalau peniaga bagi harga, sebut harga itu — angka lebih kuat daripada
          dakwaan.
        - Jangan guna emoji lebih dari satu. Kebanyakan caption tak perlu langsung.
        - Jangan ulang perkataan tawaran bulat-bulat. Tulis semula ikut suara peniaga.

        Balas JSON sahaja: ["caption satu", "caption dua"]. Tiada teks lain.
        TXT;
    }

    protected function userPrompt(string $problem, string $offer, int $count): string
    {
        return <<<TXT
        Masalah pelanggan: {$problem}
        Tawaran: {$offer}

        Bagi {$count} caption, setiap satu sudut berbeza.
        Tulis dalam suara peniaga ini, bukan suara agensi iklan.
        TXT;
    }

    /** @return array<int, string> */
    protected function fallback(string $problem, string $offer, int $count): array
    {
        $base = [
            "{$problem}?\n\n{$offer}\n\nTekan WhatsApp untuk info lanjut.",
            "Ramai peniaga hadap hal sama: {$problem}.\n\n{$offer}\n\nTekan WhatsApp untuk info lanjut.",
            "Kalau {$problem} makan masa anda setiap hari — {$offer}.\n\nTekan WhatsApp untuk info lanjut.",
            "{$offer}\n\nSesuai kalau anda masih bergelut dengan {$problem}.\n\nTekan WhatsApp untuk info lanjut.",
        ];

        return array_slice($base, 0, $count);
    }
}
