<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kredential Meta seorang peniaga.
 *
 * Peraturan mutlak #8: token tidak pernah dilog, dicommit atau ditulis dalam
 * chat. Cast `encrypted` menambah satu lagi lapisan — walaupun seseorang dapat
 * dump pangkalan data, token tetap tidak boleh dibaca tanpa APP_KEY.
 *
 * Model ini SENGAJA tidak mempunyai method yang memulangkan token dalam bentuk
 * yang senang dilog. Pengguna sebenarnya ialah MetaCredentials.
 */
class FbConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'token', 'ad_account_id', 'page_id', 'page_name',
        'wa_phone', 'meta_user_id', 'expires_at', 'status', 'last_error', 'verified_at',
    ];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Token System User boleh tiada tarikh luput langsung ("Never").
     * expires_at null bermakna tidak diketahui, BUKAN sudah luput.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** Fasa 7: peringatan 7 hari awal sebelum token luput. */
    public function expiresSoon(int $days = 7): bool
    {
        return $this->expires_at !== null
            && ! $this->isExpired()
            && $this->expires_at->diffInDays(now()) <= $days;
    }

    /** Untuk paparan sahaja — tidak pernah menunjukkan token. */
    public function label(): string
    {
        return trim(($this->page_name ?: 'Page '.$this->page_id).' · '.$this->ad_account_id);
    }
}
