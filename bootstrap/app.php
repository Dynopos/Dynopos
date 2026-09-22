<?php

use App\Exceptions\MetaCredentialsMissing;
use App\Http\Middleware\EnsureMetaConnected;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Route auth dinamakan dalam Bahasa Melayu, jadi Laravel perlu
        // diberitahu ke mana hendak menghantar pelawat yang belum masuk.
        $middleware->redirectGuestsTo(fn () => route('masuk'));
        $middleware->redirectUsersTo('/buat');

        $middleware->alias([
            'meta.connected' => EnsureMetaConnected::class,
        ]);

        // EnsureMetaConnected mesti berjalan SEBELUM SubstituteBindings.
        //
        // Livewire menyelesaikan kebergantungan mount() semasa substitute
        // bindings — Create::mount(MetaAdsService $meta) dibina di situ, iaitu
        // sebelum middleware route biasa. Tanpa baris ini, peniaga tanpa
        // kredential mendapat ralat 500 dari container, bukan alihan kemas ke
        // skrin sambung.
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            EnsureMetaConnected::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Jaring keselamatan. Middleware di atas menangkap laluan biasa, tetapi
        // ia bergantung pada route mempunyai alias `meta.connected`. Route
        // baharu yang terlupa alias itu sepatutnya menghantar peniaga ke skrin
        // sambung, bukan memaparkan ralat 500.
        $exceptions->render(function (MetaCredentialsMissing $e, Request $request) {
            if ($request->expectsJson() && ! $request->hasHeader('X-Livewire')) {
                return null;
            }

            return redirect()->route('meta.connect');
        });
    })->create();
