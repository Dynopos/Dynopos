<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bisnes yang diiklankan, dan apa yang AI faham tentangnya.
 *
 * Tanpa ini AI Nurin hanya nampak dua baris — masalah dan tawaran — lalu
 * meneka bidangnya. Bisnes sewa skylift pernah mendapat caption tentang
 * oksigen di depan pelanggan. Sekarang AI menganalisis bisnes dahulu,
 * peniaga mengesahkan, dan barulah caption ditulis.
 */
class Business extends Model
{
    use BelongsToUser, HasFactory;

    /** user_id ditetapkan oleh BelongsToUser, bukan dari borang. */
    protected $fillable = ['name', 'description'];

    protected function casts(): array
    {
        return [
            'analysis' => 'array',
            'analysis_confirmed_at' => 'datetime',
            'analysis_count' => 'integer',
        ];
    }

    public function adSets(): HasMany
    {
        return $this->hasMany(AdSet::class);
    }

    public function hasConfirmedAnalysis(): bool
    {
        return $this->analysis_confirmed_at !== null && filled($this->analysis['field'] ?? null);
    }

    public function field(): string
    {
        return (string) ($this->analysis['field'] ?? $this->name);
    }

    /** @return array<int, string> */
    public function list(string $key): array
    {
        return array_values(array_filter(array_map(
            fn ($item) => trim((string) $item),
            (array) ($this->analysis[$key] ?? [])
        )));
    }

    /** @return array<int, string> */
    public function avoidList(): array
    {
        return $this->list('avoid');
    }

    /**
     * Perkataan "jangan sebut" yang muncul dalam teks.
     *
     * Semakan kod, bukan harapan pada prompt: caption yang menyebut perkataan
     * ini ditolak walaupun AI sudah diberitahu.
     *
     * @return array<int, string>
     */
    public function offFieldTerms(string $text): array
    {
        $text = mb_strtolower($text);

        return array_values(array_filter(
            $this->avoidList(),
            fn (string $term) => preg_match('/(?<![\p{L}\p{N}])'.preg_quote(mb_strtolower($term), '/').'(?![\p{L}\p{N}])/u', $text) === 1
        ));
    }

    /** Blok konteks untuk prompt caption. */
    public function promptContext(): string
    {
        $lines = [
            'Nama bisnes: '.$this->name,
            'Bidang: '.$this->field(),
            'Apa bisnes ini buat: '.($this->analysis['summary'] ?? $this->description),
        ];

        $sections = [
            'offers' => 'Produk/servis sebenar',
            'customers' => 'Pelanggan',
            'pains' => 'Masalah pelanggan',
            'angles' => 'Sudut jualan',
            'avoid' => 'JANGAN sebut (bukan bisnes ini)',
        ];

        foreach ($sections as $key => $label) {
            if ($items = $this->list($key)) {
                $lines[] = $label.': '.implode('; ', $items);
            }
        }

        return implode("\n", $lines);
    }
}
