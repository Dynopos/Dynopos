<?php

/**
 * Deploy Forge gagal tiga kali dengan "Please provide a valid cache path."
 *
 * Config lalai Laravel: realpath(storage_path('framework/views')).
 * realpath() pulangkan FALSE bila folder tiada, dan ia dinilai masa config
 * dimuat — sebelum apa-apa service provider sempat menciptanya. Dalam deploy
 * zero-downtime Forge, `storage` ialah symlink ke folder shared yang kosong,
 * jadi composer install → package:discover mati sebelum apa-apa berjalan.
 */
it('laluan view yang dikompil tidak pernah kosong', function () {
    expect(config('view.compiled'))
        ->toBeString()
        ->not->toBeEmpty()
        ->toBe(storage_path('framework/views'));
});

it('nilai compiled tidak dibalut realpath — ia pulangkan false bila folder tiada', function () {
    $source = file_get_contents(config_path('view.php'));

    // Semak baris 'compiled' sahaja; komen dalam fail memang menyebut realpath.
    preg_match("/'compiled'\s*=>.*/", $source, $m);

    expect($m[0] ?? '')->not->toBeEmpty()->not->toContain('realpath');
});

it('Blade mengkompil walaupun folder views belum wujud', function () {
    masukSebagaiPemilik();

    $dir = storage_path('framework/views');
    $backup = $dir.'-backup-'.uniqid();

    is_dir($dir) && rename($dir, $backup);

    try {
        // Blade sepatutnya cipta folder itu sendiri semasa mengkompil.
        $this->get('/buat')->assertOk();

        expect(is_dir($dir))->toBeTrue();
    } finally {
        if (is_dir($backup)) {
            is_dir($dir) && array_map('unlink', glob("{$dir}/*") ?: []);
            is_dir($dir) && rmdir($dir);
            rename($backup, $dir);
        }
    }
});
