<?php

use App\Livewire\AdSets\Create;
use App\Livewire\AdSets\Dashboard;
use App\Livewire\AdSets\ExistingPosts;
use App\Livewire\AdSets\Review;
use App\Livewire\AdSets\Run;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Posters\Create as PosterCreate;
use App\Livewire\Settings\Connect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/masuk', Login::class)->name('masuk');
    Route::get('/daftar', Register::class)->name('daftar');
});

/*
 * Semua yang boleh membelanjakan duit atau memapar data peniaga berada di
 * belakang `auth`. Sebelum Fasa 7a tiada satu pun route dilindungi — sesiapa
 * yang tahu URL boleh membuka dashboard dan menekan RUN.
 */
Route::middleware('auth')->group(function () {
    Route::redirect('/', '/buat');

    // Setiap skrin ini bercakap dengan Meta. Tanpa kredential ia tidak boleh
    // berfungsi, jadi peniaga dihantar ke /sambung dahulu.
    Route::middleware('meta.connected')->group(function () {
        Route::get('/buat', Create::class)->name('ad-sets.create');
        Route::get('/posting', ExistingPosts::class)->name('ad-sets.existing-posts');
        Route::get('/semak/{adSet}', Review::class)->name('ad-sets.review');
        Route::get('/run/{adSet}', Run::class)->name('ad-sets.run');
        Route::get('/dashboard/{adSet}', Dashboard::class)->name('ad-sets.dashboard');

        Route::get('/poster', PosterCreate::class)->name('posters.create');
    });

    Route::get('/sambung', Connect::class)->name('meta.connect');

    Route::post('/keluar', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('masuk');
    })->name('keluar');
});
