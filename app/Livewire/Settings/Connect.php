<?php

namespace App\Livewire\Settings;

use App\Exceptions\MetaApiException;
use App\Models\FbConnection;
use App\Services\Meta\MetaCredentials;
use App\Services\MetaAdsService;
use App\Support\Impersonation;
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

    /**
     * Pemilik yang mengurus pelanggan: guna token System User dari .env.
     * Diabaikan sepenuhnya bila bukan pemilik yang mengurus — lihat sambung().
     */
    public bool $useOwnerToken = true;

    public string $consentNote = '';

    public ?string $ralat = null;

    public ?string $berjaya = null;

    public function mount(): void
    {
        $connection = Auth::user()?->fbConnection()->first();

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

        $owner = Impersonation::owner();

        // Hanya pemilik yang sedang mengurus boleh meminjam token .env. Tanpa
        // pagar ini, peniaga biasa boleh menghala token System User kita ke
        // mana-mana ad account yang ia capai — termasuk akaun pelanggan lain.
        $borrowOwnerToken = $owner !== null && $this->useOwnerToken;

        $data = $this->validate([
            'token' => $borrowOwnerToken ? ['nullable', 'string'] : ['required', 'string', 'min:40'],
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

        $token = $borrowOwnerToken ? (string) config('dynoads.meta.token') : trim((string) $data['token']);

        if ($token === '') {
            $this->ralat = 'META_ACCESS_TOKEN dalam .env kosong. Isi dulu, atau tampal token pelanggan.';

            return;
        }

        $candidate = new MetaCredentials(
            token: $token,
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
                // Bila token .env dipinjam, tiada salinan disimpan.
                'token' => $borrowOwnerToken ? null : $candidate->token,
                'uses_owner_token' => $borrowOwnerToken,
                'managed_by_user_id' => $owner?->id,
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

        if (Auth::user()->fbConnection()->first()?->needsConsent()) {
            $this->berjaya .= ' Langkah terakhir: rekod persetujuan pelanggan di bawah.';
        }
    }

    /**
     * Rekod persetujuan bertulis pelanggan — pemilik sahaja, semasa mengurus.
     *
     * Bukti disimpan sebagai teks (contohnya "Balas Setuju di WhatsApp 3 Nov")
     * supaya bila ada pertikaian tentang belanja, ada sesuatu untuk dirujuk.
     */
    public function rekodPersetujuan(): void
    {
        $this->ralat = null;
        $this->berjaya = null;

        $connection = Auth::user()?->fbConnection()->first();

        if (Impersonation::owner() === null || ! $connection instanceof FbConnection || ! $connection->isManaged()) {
            $this->ralat = 'Persetujuan hanya direkod oleh pemilik untuk pelanggan yang diurus.';

            return;
        }

        $note = trim($this->consentNote);

        if (mb_strlen($note) < 5) {
            $this->addError('consentNote', 'Tulis bukti persetujuan, contohnya: "Balas Setuju di WhatsApp 3 Nov".');

            return;
        }

        $connection->update(['consented_at' => now(), 'consent_note' => mb_substr($note, 0, 500)]);

        $this->consentNote = '';
        $this->berjaya = 'Persetujuan direkod. Iklan untuk pelanggan ni boleh dibuat sekarang.';
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
            'connection' => Auth::user()?->fbConnection()->first(),
            'pemilik' => Impersonation::owner(),
        ]);
    }
}
