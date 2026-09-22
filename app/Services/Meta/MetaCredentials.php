<?php

namespace App\Services\Meta;

use App\Exceptions\MetaCredentialsMissing;
use App\Models\FbConnection;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Empat nilai yang dahulunya terkunci dalam .env.
 *
 * Sebelum Fasa 7a, token, ad account, Page dan nombor WhatsApp datang terus
 * dari config — yang bermakna app hanya boleh melayan seorang: pemiliknya.
 * Kelas ini ialah satu-satunya tempat keempat-empat nilai itu ditentukan,
 * supaya "kredential siapa yang digunakan" boleh dijawab dengan membaca satu
 * fail.
 *
 * .env kekal sebagai fallback, tetapi HANYA untuk pemilik app (User::is_owner).
 * Lihat User::mayUseEnvCredentials() untuk sebabnya.
 */
final class MetaCredentials
{
    public function __construct(
        public readonly string $token,
        public readonly string $adAccountId,
        public readonly string $pageId,
        public readonly string $waPhone,
        /** 'connection' = kredential peniaga sendiri, 'env' = fallback pemilik. */
        public readonly string $source,
    ) {}

    /**
     * Kredential untuk user yang log masuk.
     *
     * Bila tiada sesiapa log masuk (arahan artisan, queue worker), .env
     * digunakan — itu konteks pemilik. Arahan yang memproses banyak peniaga
     * mesti memanggil forUser() dengan user yang eksplisit.
     */
    public static function current(): self
    {
        $user = Auth::user();

        return $user instanceof User ? self::forUser($user) : self::fromEnv('env');
    }

    public static function forUser(User $user): self
    {
        $connection = $user->fbConnection()->where('status', 'active')->first();

        if ($connection instanceof FbConnection) {
            return self::fromConnection($connection);
        }

        if (! $user->mayUseEnvCredentials()) {
            throw MetaCredentialsMissing::forUser();
        }

        return self::fromEnv('env');
    }

    public static function fromConnection(FbConnection $connection): self
    {
        return new self(
            token: (string) $connection->token,
            adAccountId: self::normaliseAccountId((string) $connection->ad_account_id),
            pageId: (string) $connection->page_id,
            waPhone: (string) $connection->wa_phone,
            source: 'connection',
        );
    }

    public static function fromEnv(string $source = 'env'): self
    {
        return new self(
            token: (string) config('dynoads.meta.token'),
            adAccountId: self::normaliseAccountId((string) config('dynoads.meta.ad_account_id')),
            pageId: (string) config('dynoads.meta.page_id'),
            waPhone: (string) config('dynoads.meta.wa_phone'),
            source: $source,
        );
    }

    public static function normaliseAccountId(string $id): string
    {
        return $id === '' || str_starts_with($id, 'act_') ? $id : 'act_'.$id;
    }

    public function usesOwnerFallback(): bool
    {
        return $this->source === 'env';
    }

    /**
     * Peraturan mutlak #8: token tidak pernah masuk ke dalam log atau dump.
     * PHP memanggil method ini untuk var_dump, dd() dan kebanyakan logger.
     */
    public function __debugInfo(): array
    {
        return [
            'token' => '[disembunyikan]',
            'ad_account_id' => $this->adAccountId,
            'page_id' => $this->pageId,
            'wa_phone' => $this->waPhone,
            'source' => $this->source,
        ];
    }
}
