<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'password', 'is_owner'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_owner' => 'boolean',
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
        ];
    }

    public function fbConnection(): HasOne
    {
        return $this->hasOne(FbConnection::class);
    }

    public function adSets(): HasMany
    {
        return $this->hasMany(AdSet::class);
    }

    /**
     * Hanya pemilik app jatuh balik kepada kredential .env.
     *
     * Ini bukan kemudahan — ia pagar. Tanpa pemeriksaan ini, sesiapa yang
     * mendaftar dan belum menyambung Facebook akan melancarkan iklan ke atas
     * ad account PEMILIK, dan membelanjakan duit pemilik. Peraturan mutlak #6
     * bercakap tentang satu klik pengesahan manusia; ia tidak bermakna apa-apa
     * kalau kliknya membelanjakan duit orang lain.
     */
    public function mayUseEnvCredentials(): bool
    {
        return $this->is_owner === true;
    }

    public function hasMetaCredentials(): bool
    {
        return $this->mayUseEnvCredentials() || $this->fbConnection()->where('status', 'active')->exists();
    }
}
