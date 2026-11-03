<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Pemilik "masuk sebagai" pelanggan yang dia urus, tanpa log keluar.
 *
 * Pengasingan data (BelongsToUser) dan kredential (MetaCredentials) kekal
 * bergantung pada Auth::user() — sengaja. Semasa mengurus Salon Kak Mah,
 * pemilik nampak dan membelanjakan TEPAT seperti Salon Kak Mah, tidak lebih.
 * Tiada skop atau kredential kedua yang boleh tersilap campur.
 *
 * Sesi hanya menyimpan id pemilik supaya dia boleh kembali.
 */
final class Impersonation
{
    private const KEY = 'pemilik_asal_id';

    public static function start(User $owner, User $client): void
    {
        if (! $owner->is_owner) {
            throw new InvalidArgumentException('Hanya pemilik app boleh urus akaun pelanggan.');
        }

        if ($client->is_owner || $client->is($owner)) {
            throw new InvalidArgumentException('Akaun pemilik tidak boleh diurus sebagai pelanggan.');
        }

        session()->put(self::KEY, $owner->id);
        Auth::login($client);
        session()->regenerate();
    }

    /** Kembali ke akaun pemilik. Null kalau tiada apa untuk dikembalikan. */
    public static function stop(): ?User
    {
        $owner = self::owner();
        session()->forget(self::KEY);

        if ($owner === null) {
            return null;
        }

        Auth::login($owner);
        session()->regenerate();

        return $owner;
    }

    public static function active(): bool
    {
        return self::owner() !== null;
    }

    /**
     * Pemilik yang sedang mengurus akaun ini, disahkan semula dari DB setiap kali.
     * Kalau is_owner ditarik balik, sesi itu serta-merta berhenti dikira.
     */
    public static function owner(): ?User
    {
        $id = session()->get(self::KEY);

        if ($id === null || ! Auth::check()) {
            return null;
        }

        $owner = User::find($id);

        return $owner?->is_owner ? $owner : null;
    }
}
