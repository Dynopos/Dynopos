<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Skrin pemilik sahaja. Peniaga biasa dapat 404 — tak perlu tahu skrin ni wujud. */
class EnsureOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_owner === true, 404);

        return $next($request);
    }
}
