<?php

namespace App\Services;

use App\Exceptions\BusinessNotConfirmed;
use App\Models\Business;
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
 *
 * Caption hanya ditulis untuk bisnes yang analisisnya sudah disahkan
 * peniaga (peraturan mutlak #14). Masalah dan tawaran sahaja tidak cukup —
 * AI meneka bidangnya, dan tekaan itu pernah menjadi caption oksigen untuk
 * bisnes sewa skylift.
 */
class CaptionService
{
    public function __construct(private CaptionWriter $writer) {}

    /**
     * Jana beberapa caption berbeza — satu untuk setiap gambar,
     * supaya split test membandingkan sudut ayat, bukan hanya gambar.
     *
     * @throws BusinessNotConfirmed
     */
    public function generate(Business $business, string $problem, string $offer, int $count = 1): CaptionSet
    {
        if (! $business->hasConfirmedAnalysis()) {
            throw new BusinessNotConfirmed;
        }

        $count = max(1, min($count, (int) config('dynoads.creative.max_creatives')));
        $attempts = (int) config('dynoads.caption.retries') + 1;
        $last = null;

        for ($i = 0; $i < $attempts; $i++) {
            try {
                $all = $this->ask($business, $problem, $offer, $count);

                // Semakan kod: caption yang menyebut perkara "bukan bisnes ini"
                // dibuang, walaupun prompt sudah melarangnya.
                $captions = array_values(array_filter(
                    $all,
                    fn (string $caption) => $business->offFieldTerms($caption) === []
                ));

                if (count($captions) >= $count) {
                    return CaptionSet::fromAi(array_slice($captions, 0, $count));
                }

                $last = new RuntimeException(count($captions) < count($all)
                    ? 'AI tulis caption di luar bidang bisnes.'
                    : 'Claude pulangkan caption tidak cukup.');
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
    protected function ask(Business $business, string $problem, string $offer, int $count): array
    {
        return $this->parse($this->writer->write(
            $this->systemPrompt(),
            $this->userPrompt($business, $problem, $offer, $count),
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

        KENAL BISNES DULU
        - Setiap caption mesti tentang bisnes dalam PROFIL BISNES sahaja, dalam
          bidang itu sahaja. Profil itu sudah disahkan peniaga.
        - Jangan sebut apa-apa dalam senarai "JANGAN sebut", walaupun masalah
          atau tawaran nampak berkaitan.
        - Jangan reka produk, servis, harga atau promosi yang tiada dalam
          profil, masalah atau tawaran.
        - Kalau masalah/tawaran kabur, tafsir ikut bidang bisnes, bukan ikut
          maksud lain perkataan itu.

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

    protected function userPrompt(Business $business, string $problem, string $offer, int $count): string
    {
        $profile = $business->promptContext();

        return <<<TXT
        PROFIL BISNES (disahkan peniaga):
        {$profile}

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
