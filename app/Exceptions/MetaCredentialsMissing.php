<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Peniaga belum menyambung akaun Meta dia.
 *
 * Ralat ini WUJUD supaya app berhenti sebelum memanggil Meta, bukan selepas.
 * Tanpa ia, seorang peniaga yang belum menyambung akan menggunakan kredential
 * .env — iaitu ad account pemilik app — dan membelanjakan duit orang lain.
 * Peraturan mutlak #6.
 */
class MetaCredentialsMissing extends RuntimeException
{
    public static function forUser(): self
    {
        return new self(
            'Akaun Facebook anda belum disambung. Sambungkan Page dan akaun iklan '.
            'anda dahulu sebelum membuat iklan.'
        );
    }

    public function forHuman(): string
    {
        return $this->getMessage();
    }
}
