<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Pengasingan data antara peniaga.
 *
 * Global scope dipilih dengan sengaja, bukan `where('user_id', ...)` di setiap
 * query. Sebabnya mudah: kalau pengasingan perlu diingat setiap kali seseorang
 * menulis query baharu, satu hari nanti ia akan terlupa — dan kebocorannya
 * senyap. Dengan global scope, terlupa bermakna tidak nampak apa-apa, bukan
 * nampak data orang lain.
 *
 * Kesan sampingan yang berguna: route model binding /semak/{adSet} memulangkan
 * 404 untuk id milik orang lain, bukan 403. Peniaga lain tidak dapat tahu sama
 * ada id itu wujud.
 *
 * Bila TIADA sesiapa log masuk (arahan artisan, queue worker, test), scope
 * tidak menapis apa-apa. Semua route web berada di belakang middleware `auth`,
 * jadi permintaan web sentiasa mempunyai user. Kod konsol yang memproses
 * banyak peniaga mesti menapis sendiri secara eksplisit — lihat
 * forUser() di bawah.
 */
trait BelongsToUser
{
    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope('user', function (Builder $query) {
            if (Auth::hasUser()) {
                $query->where($query->getModel()->getTable().'.user_id', Auth::id());
            }
        });

        static::creating(function ($model) {
            if ($model->user_id === null && Auth::hasUser()) {
                $model->user_id = Auth::id();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Skop eksplisit untuk konsol dan queue, di mana tiada user log masuk. */
    public function scopeForUser(Builder $query, User|int $user): Builder
    {
        return $query->withoutGlobalScope('user')
            ->where($query->getModel()->getTable().'.user_id', $user instanceof User ? $user->id : $user);
    }

    /** Baris yang belum dituntut oleh sesiapa (data sebelum Fasa 7a). */
    public function scopeUnclaimed(Builder $query): Builder
    {
        return $query->withoutGlobalScope('user')->whereNull($query->getModel()->getTable().'.user_id');
    }

    public function isOwnedBy(User|int $user): bool
    {
        return $this->user_id !== null
            && $this->user_id === ($user instanceof User ? $user->id : $user);
    }
}
