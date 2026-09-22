<?php

namespace App\Support;

/**
 * Nombor telefon Malaysia dalam bentuk yang Meta terima: 60XXXXXXXXX.
 *
 * Bentuk ini penting sejak Fasa 7a. Sebelum ini nombor yang peniaga taip tidak
 * pernah sampai ke Meta, jadi formatnya tidak menjadi hal. Sekarang ia masuk ke
 * dalam promoted_object, dan Meta menolak apa-apa yang bukan digit tanpa +.
 *
 * Orang menaip nombor mereka dengan pelbagai cara — 012-345 6789,
 * +6012 345 6789, 0123456789 — dan ketiga-tiganya nombor yang sama.
 */
class Phone
{
    /** Pulangkan 60XXXXXXXXX, atau null kalau ia bukan nombor Malaysia yang munasabah. */
    public static function normalise(?string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $input) ?? '';

        if ($digits === '') {
            return null;
        }

        // 0123456789 → 60123456789
        if (str_starts_with($digits, '0')) {
            $digits = '60'.substr($digits, 1);
        } elseif (! str_starts_with($digits, '60')) {
            // 123456789 ditaip tanpa 0 di depan.
            $digits = '60'.$digits;
        }

        // 60 + 9 hingga 10 digit. Talian tetap dan mudah alih kedua-duanya muat.
        return preg_match('/^60\d{8,10}$/', $digits) === 1 ? $digits : null;
    }

    /** 60123456789 → 012-345 6789, untuk paparan sahaja. */
    public static function pretty(?string $input): string
    {
        $n = self::normalise($input);

        if ($n === null) {
            return (string) $input;
        }

        $local = '0'.substr($n, 2);

        return strlen($local) >= 10
            ? substr($local, 0, 3).'-'.substr($local, 3, 3).' '.substr($local, 6)
            : substr($local, 0, 3).'-'.substr($local, 3);
    }
}
