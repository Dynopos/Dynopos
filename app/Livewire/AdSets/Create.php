<?php

namespace App\Livewire\AdSets;

use App\Exceptions\MetaApiException;
use App\Models\AdSet;
use App\Models\AdVariant;
use App\Services\ImageProcessor;
use App\Services\Meta\MetaCredentials;
use App\Services\MetaAdsService;
use App\Services\Poster\PosterBasket;
use App\Services\Poster\ProductHandoff;
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
     * Kotak pilih fail. Atas telefon, galeri selalunya pulangkan satu gambar
     * setiap kali — jadi medan ini dikosongkan setiap kali dan isinya
     * dipindahkan ke $images. Kalau tidak, pilihan kedua MENGGANTIKAN yang
     * pertama dan peniaga hanya dapat satu gambar tanpa tahu kenapa.
     *
     * @var array<int, TemporaryUploadedFile>
     */
    public array $upload = [];

    /** Gambar yang dikumpul setakat ini. @var array<int, TemporaryUploadedFile> */
    public array $images = [];

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

    /** Kumpul gambar merentas beberapa kali pilih, jangan ganti. */
    public function updatedUpload(): void
    {
        $max = (int) config('dynoads.creative.max_images');

        foreach ($this->upload as $file) {
            if ($this->creativeCount() >= $max) {
                break;
            }

            $this->images[] = $file;
        }

        $this->upload = [];
        $this->resetValidation('images');
    }

    public function removeImage(int $index): void
    {
        unset($this->images[$index]);
        $this->images = array_values($this->images);
    }

    /**
     * Bawa gambar ni ke skrin poster sebagai gambar produk.
     *
     * Ia dikeluarkan dari senarai gambar biasa — kalau tidak, peniaga akan
     * berakhir dengan dua creative dari satu gambar: yang mentah dan posternya.
     */
    public function makePoster(int $index, ProductHandoff $handoff)
    {
        if (! isset($this->images[$index])) {
            return null;
        }

        $handoff->put($this->images[$index]->getRealPath());
        $this->removeImage($index);

        return $this->redirectRoute('posters.create', navigate: true);
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

    /** Poster dari /poster dikira sebagai creative, sama seperti gambar upload. */
    public function posterJobs()
    {
        return app(PosterBasket::class)->jobs();
    }

    public function uploadLimit(): string
    {
        return Uploads::maxLabel();
    }

    public function uploadLimitTooTight(): bool
    {
        return Uploads::tooTightForPhonePhotos();
    }

    public function creativeCount(): int
    {
        return count($this->images) + $this->posterJobs()->count();
    }

    public function removePoster(int $posterJobId, PosterBasket $basket): void
    {
        $basket->remove($posterJobId);
    }

    protected function rules(): array
    {
        $min = intdiv((int) config('dynoads.budget.min_daily_sen'), 100);
        $max = intdiv((int) config('dynoads.budget.max_daily_sen'), 100);

        return [
            'images' => 'array|max:'.config('dynoads.creative.max_images'),
            'images.*' => 'image|max:'.Uploads::maxKilobytes(),
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
            'images.required' => 'Muat naik sekurang-kurangnya satu gambar.',
            'creatives.required' => 'Perlukan sekurang-kurangnya satu gambar atau poster.',
            'images.max' => 'Maksimum 4 gambar. Lebih dari tu susah nak baca hasilnya.',
            'images.*.image' => 'Fail kena gambar (JPG, PNG atau WEBP).',
            'images.*.uploaded' => 'Gambar gagal dimuat naik. Biasanya kerana saiznya melebihi had pelayan ('.Uploads::maxLabel().').',
            'images.*.max' => 'Gambar terlalu besar. Had pelayan ni :max KB.',
            'upload.*.uploaded' => 'Gambar gagal dimuat naik. Biasanya kerana saiznya melebihi had pelayan ('.Uploads::maxLabel().').',
            'problem.required' => 'Tulis masalah pelanggan anda.',
            'offer.required' => 'Tulis apa yang anda tawarkan.',
            'phone.regex' => 'Nombor WhatsApp kena format 60XXXXXXXXX, tiada tanda +.',
        ];
    }

    public function totalDailyRm(): int
    {
        return max($this->creativeCount(), 1) * $this->budgetRm;
    }

    public function save(ImageProcessor $processor, PosterBasket $basket)
    {
        $this->validate();

        $posters = $basket->jobs();

        if ($this->images === [] && $posters->isEmpty()) {
            $this->addError('images', 'Perlukan sekurang-kurangnya satu gambar atau poster.');

            return null;
        }

        if (count($this->images) + $posters->count() > (int) config('dynoads.creative.max_images')) {
            $this->addError('images', 'Maksimum '.config('dynoads.creative.max_images').' creative semuanya, termasuk poster.');

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

        // Poster dahulu — itu yang peniaga sengaja reka.
        foreach ($posters as $job) {
            $relative = "dynoads/{$set->id}/".(++$position).'.jpg';
            $disk->makeDirectory(dirname($relative));

            // Salin, bukan rujuk: poster boleh dijana semula atau dipadam,
            // tetapi creative iklan mesti kekal seperti masa ia dilancarkan.
            $processor->squareCrop($disk->path($job->output_path), $disk->path($relative));

            AdVariant::create([
                'ad_set_id' => $set->id,
                'position' => $position,
                'source_type' => 'poster',
                'poster_job_id' => $job->id,
                'image_path' => $relative,
            ]);
        }

        foreach (array_values($this->images) as $upload) {
            $relative = "dynoads/{$set->id}/".(++$position).'.jpg';
            $disk->makeDirectory(dirname($relative));

            $processor->squareCrop($upload->getRealPath(), $disk->path($relative));

            AdVariant::create([
                'ad_set_id' => $set->id,
                'position' => $position,
                'source_type' => 'upload',
                'image_path' => $relative,
            ]);
        }

        $basket->clear();

        return $this->redirectRoute('ad-sets.review', ['adSet' => $set], navigate: true);
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
