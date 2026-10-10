<?php

namespace App\Http\Controllers;

use App\Models\DataDeletionRequest;
use App\Models\FbConnection;
use App\Services\Meta\SignedRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Callback pemadaman data Meta.
 *
 * Bila seseorang membuang DynoAds daripada tetapan Facebook dia dan memilih
 * "padam data saya", Meta POST ke sini. App WAJIB memadam data yang diperoleh
 * daripada Facebook, dan memulangkan URL status serta kod pengesahan.
 *
 * Ia juga memenuhi hak subjek data di bawah PDPA Akta 709 — hak menarik balik
 * consent dan hak pemadaman.
 *
 * Endpoint ini TIADA auth dan TIADA CSRF, kerana Meta yang memanggilnya.
 * Satu-satunya yang menjaganya ialah tandatangan HMAC pada signed_request.
 * Sebab tu SignedRequest menolak semuanya bila rahsia app tiada.
 *
 * Kelas biasa, bukan subkelas Controller: repo ini Livewire sepenuhnya dan
 * tiada base controller. Menambah satu semata-mata untuk dua method ialah
 * perancah yang tidak digunakan oleh apa-apa lain.
 */
class MetaDataDeletionController
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $payload = SignedRequest::parse((string) $request->input('signed_request'));
        } catch (Throwable) {
            // Sebabnya tidak dikembalikan kepada pemanggil. Membezakan
            // "tandatangan salah" daripada "muatan rosak" memberi petunjuk
            // kepada sesiapa yang sedang meneka.
            return response()->json(['error' => 'Permintaan tidak sah.'], 400);
        }

        $metaUserId = (string) ($payload['user_id'] ?? '');

        if ($metaUserId === '') {
            return response()->json(['error' => 'Permintaan tidak sah.'], 400);
        }

        $record = $this->deleteFacebookData($metaUserId);

        // Bentuk respons ini ditetapkan oleh Meta: kedua-dua medan wajib ada,
        // kalau tidak permintaan dianggap gagal dan dicuba berulang kali.
        return response()->json([
            'url' => route('meta.data-deletion.status', $record->confirmation_code),
            'confirmation_code' => $record->confirmation_code,
        ]);
    }

    /**
     * Padam semua yang datang dari Facebook untuk pengguna ini.
     *
     * Yang dipadam: FbConnection — token akses, id akaun iklan, id dan nama
     * Page, nombor WhatsApp. Itu keseluruhan data yang app ini peroleh
     * daripada Facebook.
     *
     * Yang TIDAK dipadam: set iklan dan metrik peniaga. Itu rekod perniagaan
     * dia sendiri, bukan data Facebook, dan memadamnya atas satu callback akan
     * memusnahkan sejarah yang mungkin dia perlukan. Halaman status
     * menerangkan perbezaan ini dan cara meminta pemadaman akaun penuh.
     */
    protected function deleteFacebookData(string $metaUserId): DataDeletionRequest
    {
        return DB::transaction(function () use ($metaUserId) {
            $connection = FbConnection::where('meta_user_id', $metaUserId)->first();

            if (! $connection) {
                return DataDeletionRequest::create([
                    'confirmation_code' => DataDeletionRequest::newCode(),
                    'meta_user_id' => $metaUserId,
                    'status' => 'no_match',
                    'summary' => 'Tiada data Facebook untuk akaun ini dalam sistem.',
                    'completed_at' => now(),
                ]);
            }

            $userId = $connection->user_id;
            $connection->delete();

            return DataDeletionRequest::create([
                'confirmation_code' => DataDeletionRequest::newCode(),
                'meta_user_id' => $metaUserId,
                'user_id' => $userId,
                'status' => 'completed',
                'summary' => 'Sambungan Facebook dipadam: token akses, id akaun iklan, '
                    .'id dan nama Page, serta nombor WhatsApp.',
                'completed_at' => now(),
            ]);
        });
    }

    /** Halaman status awam yang Meta dan peniaga boleh semak. */
    public function status(string $code): View
    {
        return view('legal.data-deletion-status', [
            'record' => DataDeletionRequest::where('confirmation_code', $code)->first(),
        ]);
    }
}
