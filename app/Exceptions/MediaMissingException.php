<?php

namespace App\Exceptions;

use Carbon\CarbonInterface;
use RuntimeException;

/**
 * Fail media variant tiada di cakera.
 *
 * Sebab paling biasa: ia dibuang oleh dynoads:prune-media selepas tempoh
 * simpanan tamat, kemudian peniaga cuba melancarkan set iklan lama itu.
 *
 * Pengecualian berasingan, bukan MetaApiException: ini bukan ralat Meta, dan
 * melabelkannya begitu akan menghantar sesiapa yang menyiasat ke arah yang
 * salah sepenuhnya.
 */
class MediaMissingException extends RuntimeException
{
    public static function forVariant(int $position, ?CarbonInterface $prunedAt = null): self
    {
        $bila = $prunedAt ? ' pada '.$prunedAt->format('d M Y') : '';

        return new self(
            "Fail untuk iklan #{$position} sudah tiada{$bila}. ".
            'Media dibuang selepas tempoh simpanan tamat — muat naik semula gambar '.
            'atau video untuk melancarkan iklan ini.'
        );
    }

    public function forHuman(): string
    {
        return $this->getMessage();
    }
}
