<?php

namespace App\Livewire\Settings;

use App\Exceptions\MetaApiException;
use App\Models\FbConnection;
use App\Services\Meta\MetaCredentials;
use App\Services\MetaAdsService;
use App\Support\Phone;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Sambung akaun Meta peniaga.
 *
 * Skrin ini SEMENTARA. Menaip token System User bukan sesuatu yang peniaga
 * biasa boleh buat — ia memerlukan Business Manager, pemahaman tentang app dan
 * assigned assets. Fasa 7b menggantikan borang ini dengan satu butang
 * "Sambung Facebook" (Facebook Login for Business), dan pada masa itu hanya
 * cara baris FbConnection DIISI yang berubah. Semua yang lain — pengasingan,
 * enkripsi, MetaCredentials — sudah siap di sini.
 *
 * Sehingga itu, borang ini untuk pemilik app yang menyediakan akaun bagi
 * peniaga perintis, bukan untuk peniaga mengisi sendiri.
 */
#[Layout('layouts.app')]
#[Title('Sambung akaun Meta')]
class Connect extends Component
{
    public string $token = '';

    public string $adAccountId = '';

    public string $pageId = '';

    public string $waPhone = '';

    public ?string $ralat = null;

    public ?string $berjaya = null;

    public function mount(): void
    {
        $connection = Auth::user()?->fbConnection;

        if ($connection instanceof FbConnection) {
            // Token TIDAK diisi semula ke dalam borang. Peraturan mutlak #8:
            // sekali ia masuk, ia tidak keluar semula ke skrin.
            $this->adAccountId = (string) $connection->ad_account_id;
            $this->pageId = (string) $connection->page_id;
            $this->waPhone = Phone::pretty($connection->wa_phone);
        }
    }

    public function sambung(): void
    {
        $this->ralat = null;
        $this->berjaya = null;

        $data = $this->validate([
            'token' => ['required', 'string', 'min:40'],
            'adAccountId' => ['required', 'string'],
            'pageId' => ['required', 'string'],
            'waPhone' => ['required', 'string'],
        ], [
            'token.required' => 'Tampal token akaun Meta anda.',
            'token.min' => 'Token tu nampak terlalu pendek. Pastikan anda salin kesemuanya.',
            'adAccountId.required' => 'Isi ID akaun iklan (bermula dengan act_).',
            'pageId.required' => 'Isi ID Page anda.',
            'waPhone.required' => 'Isi nombor WhatsApp untuk terima lead.',
        ]);

        $phone = Phone::normalise($data['waPhone']);

        if ($phone === null) {
            $this->addError('waPhone', 'Nombor tu nampak tak betul. Contoh: 012-345 6789.');

            return;
        }

        $candidate = new MetaCredentials(
            token: trim($data['token']),
            adAccountId: MetaCredentials::normaliseAccountId(trim($data['adAccountId'])),
            pageId: trim($data['pageId']),
            waPhone: $phone,
            source: 'connection',
        );

        // Disahkan DAHULU, disimpan kemudian. Kredential yang tidak berfungsi
        // tidak sepatutnya duduk dalam pangkalan data kelihatan seperti siap.
        try {
            $nama = MetaAdsService::fromCredentials($candidate)->verifyAccess();
        } catch (MetaApiException $e) {
            $this->ralat = $this->terangkan($e);

            return;
        } catch (ConnectionException) {
            $this->ralat = 'Tak dapat hubungi Meta sekarang. Cuba lagi sekejap.';

            return;
        }

        FbConnection::updateOrCreate(
            ['user_id' => Auth::id()],
            [
                'token' => $candidate->token,
                'ad_account_id' => $candidate->adAccountId,
                'page_id' => $candidate->pageId,
                'page_name' => $nama['page'],
                'wa_phone' => $candidate->waPhone,
                'status' => 'active',
                'last_error' => null,
                'verified_at' => now(),
            ]
        );

        $this->token = '';
        $this->berjaya = "Siap. Akaun iklan \"{$nama['ad_account']}\" dan Page \"{$nama['page']}\" dah disambung.";
    }

    /**
     * Terjemah ralat Meta kepada ayat yang memberitahu apa nak buat.
     *
     * Kod 190 bermakna token rosak atau luput; 200, 2500 dan 102 muncul apabila
     * token kosong atau tidak mempunyai akses kepada aset yang diminta. Mesej
     * asal Meta ("Provide valid app ID") tidak membantu sesiapa.
     */
    protected function terangkan(MetaApiException $e): string
    {
        return match ($e->errorCode) {
            190 => 'Token tu tak sah atau dah luput. Jana token baharu di Meta Business Settings.',
            102, 200, 2500 => 'Token tu tak dapat capai akaun iklan atau Page yang anda masukkan. '
                .'Pastikan system user anda ada Assigned Assets untuk kedua-duanya.',
            803 => 'ID akaun iklan atau ID Page tu tak wujud. Semak semula nombornya.',
            default => 'Meta tolak kredential ni: '.$e->forHuman(),
        };
    }

    public function render()
    {
        return view('livewire.settings.connect', [
            'connection' => Auth::user()?->fbConnection,
        ]);
    }
}
