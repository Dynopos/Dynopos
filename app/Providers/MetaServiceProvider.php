<?php

namespace App\Providers;

use App\Services\Meta\MetaCredentials;
use App\Services\MetaAdsService;
use App\Services\PagePostService;
use Illuminate\Support\ServiceProvider;

/**
 * Menyambungkan kredential peniaga kepada dua service yang bercakap dengan Meta.
 *
 * Diikat dengan bind(), BUKAN singleton. Satu proses queue worker melayan
 * banyak peniaga berturut-turut; singleton akan membawa token peniaga pertama
 * ke dalam kerja peniaga kedua.
 */
class MetaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MetaAdsService::class, fn () => MetaAdsService::fromCredentials(MetaCredentials::current()));
        $this->app->bind(PagePostService::class, fn () => PagePostService::fromCredentials(MetaCredentials::current()));
    }
}
