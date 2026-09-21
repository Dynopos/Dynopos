<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Gambar atau video — satu tempat yang tahu bezanya.
 *
 * Dua had saiz yang berbeza, dua senarai sambungan fail, dua laluan berlainan
 * di Meta. Tanpa kelas ini, pemeriksaan "ini video ke?" akan bertaburan dan
 * satu daripadanya akan terlepas.
 */
class Media
{
    public static function isVideo(UploadedFile $file): bool
    {
        return in_array(self::extension($file), self::videoExtensions(), true);
    }

    public static function isImage(UploadedFile $file): bool
    {
        return in_array(self::extension($file), self::imageExtensions(), true);
    }

    /**
     * Sebab fail ini tidak boleh diterima, atau null kalau ia elok.
     *
     * Dipanggil semasa peniaga memilih fail, bukan semasa dia menekan simpan.
     * Memberitahu awal bermakna dia boleh pilih fail lain sekarang, bukan
     * selepas mengisi seluruh borang.
     */
    public static function reject(UploadedFile $file): ?string
    {
        $name = $file->getClientOriginalName();

        if (! self::isVideo($file) && ! self::isImage($file)) {
            return "\"{$name}\" bukan gambar atau video yang kami boleh guna. Guna JPG, PNG, WEBP, MP4 atau MOV.";
        }

        $kilobytes = (int) ceil($file->getSize() / 1024);
        $limit = self::isVideo($file)
            ? Uploads::maxKilobytes(self::maxVideoKilobytes())
            : Uploads::maxKilobytes();

        if ($kilobytes > $limit) {
            return sprintf(
                '"%s" terlalu besar (%s). Had pelayan ni %s.',
                $name,
                self::label($kilobytes),
                self::label($limit),
            );
        }

        return null;
    }

    public static function maxVideoKilobytes(): int
    {
        return (int) config('dynoads.video.max_kilobytes', 102400);
    }

    /** @return array<int, string> */
    public static function videoExtensions(): array
    {
        return array_map('strtolower', (array) config('dynoads.creative.video_mimes', ['mp4', 'mov', 'm4v']));
    }

    /** @return array<int, string> */
    public static function imageExtensions(): array
    {
        return array_map('strtolower', (array) config('dynoads.creative.image_mimes', ['jpg', 'jpeg', 'png', 'webp']));
    }

    /** Untuk atribut accept= pada input fail. */
    public static function acceptAttribute(): string
    {
        return collect([...self::imageExtensions(), ...self::videoExtensions()])
            ->map(fn (string $ext) => '.'.$ext)
            ->implode(',');
    }

    protected static function extension(UploadedFile $file): string
    {
        // getClientOriginalExtension() boleh kosong bila fail datang dari
        // kamera telefon tanpa nama. Sambungan fail sementara jadi sandaran.
        return strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
    }

    protected static function label(int $kilobytes): string
    {
        return $kilobytes >= 1024
            ? rtrim(rtrim(number_format($kilobytes / 1024, 1), '0'), '.').' MB'
            : $kilobytes.' KB';
    }
}
