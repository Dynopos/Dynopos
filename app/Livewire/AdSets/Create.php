<?php

namespace App\Livewire\AdSets;

use App\Exceptions\MetaApiException;
use App\Models\AdSet;
use App\Models\AdVariant;
use App\Services\ImageProcessor;
use App\Services\Meta\MetaCredentials;
use App\Services\MetaAdsService;
use App\Support\Media;
use App\Support\Uploads;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Buat iklan baru')]
class Create extends Component
{
    use WithFileUploads;

    /**
     * Kotak pilih fail. Atas telefon, galeri selalunya pulangkan satu fail
     * setiap kali — jadi medan ini dikosongkan setiap kali dan isinya
     * dipindahkan ke $media. Kalau tidak, pilihan kedua MENGGANTIKAN yang
     * pertama dan peniaga hanya dapat satu media tanpa tahu kenapa.
     *
     * @var array<int, TemporaryUploadedFile>
     */
    public array $upload = [];

    /**
     * Gambar dan video yang dikumpul setakat ini.
     *
     * Satu senarai, bukan dua. Peniaga fikir "empat iklan", bukan "dua gambar
     * dan dua video", dan satu media tetap satu campaign tanpa mengira jenis.
     *
     * @var array<int, TemporaryUploadedFile>
     */
    public array $media = [];

    public string $problem = '';

    public string $offer = '';

    public string $phone = '';

    /** @var array<int, string> */
    public array $regionKeys = [];

    public int $budgetRm = 37;

    /** @var array<int, array{key:string,name:string}> */
    public array $regions = [];

    public ?string $regionError = null;

    public function mount(MetaAdsService $meta): void
    {
        // Dari sambungan peniaga sendiri. Sebelum Fasa 7a ini membaca .env,
        // jadi setiap peniaga nampak nombor WhatsApp pemilik app sebagai lalai.
        $this->phone = MetaCredentials::current()->waPhone;
        $this->budgetRm = intdiv((int) config('dynoads.budget.default_daily_sen'), 100);

        try {
            $this->regions = $meta->searchRegions();
        } catch (\Throwable $e) {
            // Senarai negeri datang dari Meta. Tanpa token yang sah ia gagal —
            // katakan sebabnya, jangan biarkan senarai kosong tanpa penjelasan.
            $this->regions = [];
            $this->regionError = $this->safeReason($e);
        }
    }

    /**
     * Sebab kegagalan yang selamat dipapar.
     *
     * Mesej pengecualian HTTP membawa URL penuh yang dipanggil — dan URL Graph
     * API mengandungi ?access_token=. Peraturan mutlak #8: token tidak pernah
     * dipapar, dilog atau ditulis ke mana-mana. Jadi kita tidak pernah
     * memaparkan mesej mentah; kita pulangkan ayat pendek kita sendiri.
     */
    private function safeReason(\Throwable $e): string
    {
        return match (true) {
            $e instanceof MetaApiException => $e->forHuman(),
            $e instanceof ConnectionException => 'Tidak dapat hubungi Meta dari pelayan ini.',
            default => 'Panggilan ke Meta gagal ('.class_basename($e).').',
        };
    }

    /** Kumpul media merentas beberapa kali pilih, jangan ganti. */
    public function updatedUpload(): void
    {
        $max = (int) config('dynoads.creative.max_creatives');

        foreach ($this->upload as $file) {
            if ($this->creativeCount() >= $max) {
                break;
            }

            if ($problem = Media::reject($file)) {
                $this->addError('media', $problem);

                continue;
            }

            $this->media[] = $file;
        }

        $this->upload = [];
    }

    public function removeMedia(int $index): void
    {
        unset($this->media[$index]);
        $this->media = array_values($this->media);
    }

    public function toggleRegion(string $key): void
    {
        $this->regionKeys = in_array($key, $this->regionKeys, true)
            ? array_values(array_diff($this->regionKeys, [$key]))
            : [...$this->regionKeys, $key];
    }

    public function clearRegions(): void
    {
        $this->regionKeys = [];
    }

    public function isVideo(int $index): bool
    {
        return isset($this->media[$index]) && Media::isVideo($this->media[$index]);
    }

    public function uploadLimit(): string
    {
        return Uploads::maxLabel();
    }

    public function uploadLimitTooTight(): bool
    {
        return Uploads::tooTightForPhonePhotos();
    }

    /**
     * Had pelayan terlalu ketat untuk video telefon biasa?
     *
     * Video 30 saat dari telefon selalunya 20–60 MB. Kalau php.ini hanya
     * membenarkan 8 MB, butang "tambah video" adalah janji kosong — jadi
     * skrin memberitahu awal, bukan selepas peniaga menunggu muat naik gagal.
     */
    public function videoLimitTooTight(): bool
    {
        return Uploads::maxKilobytes(Media::maxVideoKilobytes()) < 25600;
    }

    public function creativeCount(): int
    {
        return count($this->media);
    }

    protected function rules(): array
    {
        $min = intdiv((int) config('dynoads.budget.min_daily_sen'), 100);
        $max = intdiv((int) config('dynoads.budget.max_daily_sen'), 100);

        return [
            'media' => 'array|max:'.config('dynoads.creative.max_creatives'),
            'problem' => 'required|string|min:5|max:200',
            'offer' => 'required|string|min:5|max:200',
            'phone' => ['required', 'regex:/^60\d{8,11}$/'],
            'regionKeys' => 'array',
            'regionKeys.*' => 'string',
            'budgetRm' => "required|integer|min:{$min}|max:{$max}",
        ];
    }

    protected function messages(): array
    {
        return [
            'media.max' => 'Maksimum 4 media. Lebih dari tu susah nak baca hasilnya.',
            'upload.*.uploaded' => 'Fail gagal dimuat naik. Biasanya kerana saiznya melebihi had pelayan ('.Uploads::maxLabel().').',
            'problem.required' => 'Tulis masalah pelanggan anda.',
            'offer.required' => 'Tulis apa yang anda tawarkan.',
            'phone.regex' => 'Nombor WhatsApp kena format 60XXXXXXXXX, tiada tanda +.',
        ];
    }

    public function totalDailyRm(): int
    {
        return max($this->creativeCount(), 1) * $this->budgetRm;
    }

    public function save(ImageProcessor $processor)
    {
        $this->validate();

        if ($this->media === []) {
            $this->addError('media', 'Tambah sekurang-kurangnya satu gambar atau video.');

            return null;
        }

        $set = AdSet::create([
            'name' => str($this->offer)->limit(60)->value(),
            'problem' => $this->problem,
            'offer' => $this->offer,
            'phone' => $this->phone,
            'region_keys' => $this->regionKeys,
            'region_names' => $this->regionNames(),
            'daily_budget_sen' => $this->budgetRm * 100,
            'status' => 'draft',
        ]);

        $position = 0;
        $disk = Storage::disk('public');

        foreach (array_values($this->media) as $file) {
            $position++;
            $disk->makeDirectory("dynoads/{$set->id}");

            AdVariant::create(
                Media::isVideo($file)
                    ? $this->storeVideo($file, $set, $position)
                    : $this->storeImage($file, $set, $position, $processor)
            );
        }

        return $this->redirectRoute('ad-sets.review', ['adSet' => $set], navigate: true);
    }

    /** Gambar dipotong 1080×1080 seperti sebelum ini. */
    protected function storeImage(TemporaryUploadedFile $file, AdSet $set, int $position, ImageProcessor $processor): array
    {
        $relative = "dynoads/{$set->id}/{$position}.jpg";

        $processor->squareCrop($file->getRealPath(), Storage::disk('public')->path($relative));

        return [
            'ad_set_id' => $set->id,
            'position' => $position,
            'source_type' => 'upload',
            'media_type' => 'image',
            'image_path' => $relative,
        ];
    }

    /**
     * Video disimpan seadanya.
     *
     * Tiada crop, tiada transcode: pelayan ini tiada ffmpeg, dan Meta sendiri
     * menerima nisbah selain persegi untuk video. Memaksa 1:1 di sini bermakna
     * memotong kepala orang dalam video menegak yang peniaga rakam sendiri.
     */
    protected function storeVideo(TemporaryUploadedFile $file, AdSet $set, int $position): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'mp4');
        $relative = "dynoads/{$set->id}/{$position}.{$extension}";

        Storage::disk('public')->put($relative, file_get_contents($file->getRealPath()));

        return [
            'ad_set_id' => $set->id,
            'position' => $position,
            'source_type' => 'upload',
            'media_type' => 'video',
            'video_path' => $relative,
        ];
    }

    /** @return array<int, string> */
    protected function regionNames(): array
    {
        return collect($this->regions)
            ->whereIn('key', $this->regionKeys)
            ->pluck('name')
            ->all();
    }

    public function render()
    {
        return view('livewire.ad-sets.create');
    }
}
