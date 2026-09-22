<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Peniaga tanpa kredential Meta dihantar ke skrin sambung, bukan ke dalam
 * borang yang akan gagal di hujung.
 *
 * Tanpa pagar ini, seorang peniaga baharu boleh mengisi seluruh borang, memuat
 * naik gambar, menekan Approve — dan barulah nampak ralat. Lebih teruk lagi:
 * kalau fallback .env terpakai untuk sesiapa selain pemilik, iklannya akan
 * dibuat di atas akaun pemilik dan membelanjakan duit pemilik.
 */
class EnsureMetaConnected
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->hasMetaCredentials()) {
            return redirect()->route('meta.connect');
        }

        return $next($request);
    }
}
