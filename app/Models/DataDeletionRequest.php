<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DataDeletionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'confirmation_code', 'meta_user_id', 'user_id', 'status', 'summary', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Kod yang peniaga boleh guna untuk menyemak status permintaannya.
     *
     * Ia muncul dalam URL awam, jadi ia mesti tidak boleh diteka — kalau tidak
     * sesiapa boleh menyenaraikan permintaan pemadaman orang lain dengan
     * mencuba nombor berturutan.
     */
    public static function newCode(): string
    {
        return Str::lower(Str::random(32));
    }

    public function isDone(): bool
    {
        return in_array($this->status, ['completed', 'no_match'], true);
    }
}
