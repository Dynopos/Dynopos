<?php

namespace App\Services\Meta;

use RuntimeException;

/**
 * Pengesahan `signed_request` Meta.
 *
 * Meta menghantar permintaan pemadaman data sebagai satu rentetan bertanda:
 * "{tandatangan}.{muatan}", kedua-duanya base64url. Tandatangan ialah
 * HMAC-SHA256 muatan itu menggunakan rahsia app.
 *
 * Kenapa pengesahan ini WAJIB, bukan pilihan: endpoint pemadaman terdedah
 * kepada internet terbuka tanpa log masuk. Tanpa semakan tandatangan, sesiapa
 * yang tahu URL boleh menghantar permintaan dan memadam data peniaga.
 *
 * Bila rahsia app tiada dalam .env, kelas ini menolak SEMUA permintaan.
 * Gagal tertutup, bukan gagal terbuka — endpoint yang tidak berfungsi jauh
 * lebih baik daripada endpoint yang memadam data atas arahan orang asing.
 */
final class SignedRequest
{
    /**
     * @return array<string, mixed> muatan yang sudah disahkan
     *
     * @throws RuntimeException bila tandatangan tidak sah
     */
    public static function parse(string $signedRequest, ?string $appSecret = null): array
    {
        $secret = (string) ($appSecret ?? config('dynoads.meta.app_secret'));

        if ($secret === '') {
            throw new RuntimeException('META_APP_SECRET tidak ditetapkan — permintaan tidak boleh disahkan.');
        }

        if (! str_contains($signedRequest, '.')) {
            throw new RuntimeException('signed_request tidak berbentuk {tandatangan}.{muatan}.');
        }

        [$encodedSignature, $encodedPayload] = explode('.', $signedRequest, 2);

        $signature = self::base64UrlDecode($encodedSignature);
        $payload = self::base64UrlDecode($encodedPayload);

        if ($signature === '' || $payload === '') {
            throw new RuntimeException('signed_request tidak boleh dinyahkod.');
        }

        // hash_equals, bukan ===. Perbandingan biasa keluar awal pada bait
        // pertama yang berbeza, dan perbezaan masa itu cukup untuk meneka
        // tandatangan satu bait pada satu masa.
        $expected = hash_hmac('sha256', $encodedPayload, $secret, true);

        if (! hash_equals($expected, $signature)) {
            throw new RuntimeException('Tandatangan signed_request tidak sah.');
        }

        $data = json_decode($payload, true);

        if (! is_array($data)) {
            throw new RuntimeException('Muatan signed_request bukan JSON yang sah.');
        }

        if (strtoupper((string) ($data['algorithm'] ?? '')) !== 'HMAC-SHA256') {
            throw new RuntimeException('Algoritma signed_request tidak disokong.');
        }

        return $data;
    }

    protected static function base64UrlDecode(string $value): string
    {
        $padded = strtr($value, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);

        return (string) base64_decode($padded, true);
    }
}
