<?php

namespace App\Exceptions;

use RuntimeException;

/** Caption diminta untuk bisnes yang analisisnya belum disahkan peniaga. */
class BusinessNotConfirmed extends RuntimeException
{
    public const MESSAGE = 'Sahkan analisis bisnes dulu sebelum AI Nurin tulis caption.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
