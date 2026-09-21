<?php

use App\Livewire\AdSets\Create;
use App\Livewire\AdSets\Dashboard;
use App\Livewire\AdSets\ExistingPosts;
use App\Livewire\AdSets\Review;
use App\Livewire\AdSets\Run;
use App\Livewire\Posters\Create as PosterCreate;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/buat');

Route::get('/buat', Create::class)->name('ad-sets.create');
Route::get('/posting', ExistingPosts::class)->name('ad-sets.existing-posts');
Route::get('/semak/{adSet}', Review::class)->name('ad-sets.review');
Route::get('/run/{adSet}', Run::class)->name('ad-sets.run');
Route::get('/dashboard/{adSet}', Dashboard::class)->name('ad-sets.dashboard');

Route::get('/poster', PosterCreate::class)->name('posters.create');
