<?php

namespace App\Exceptions;

/**
 * Pelanggan yang diurus pemilik belum memberi persetujuan bertulis.
 *
 * Mewarisi MetaCredentialsMissing dengan sengaja: setiap laluan yang sudah
 * berhenti bila kredential tiada — middleware, pengendali exception, binding
 * MetaAdsService — kini juga berhenti di sini, sebelum satu pun panggilan ke
 * Meta. Peraturan mutlak #6: duit pelanggan, kad pelanggan.
 */
class ConsentMissing extends MetaCredentialsMissing
{
    public static function forClient(): self
    {
        return new self(
            'Pelanggan ni belum beri persetujuan bertulis. Rekod persetujuan di skrin '.
            'sambung dulu sebelum buat iklan.'
        );
    }
}
