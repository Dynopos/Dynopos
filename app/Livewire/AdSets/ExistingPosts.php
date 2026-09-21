<?php

namespace App\Livewire\AdSets;

use App\Exceptions\MetaApiException;
use App\Models\AdSet;
use App\Models\AdVariant;
use App\Services\AdLauncher;
use App\Services\ImageProcessor;
use App\Services\MetaAdsService;
use App\Services\PagePostService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/**
 * Guna posting yang peniaga sudah ada di Page-nya.
 *
 * Ramai peniaga sudah ada puluhan posting. Yang mereka tak tahu ialah posting
 * mana yang berbaloi dibelanjakan duit — dan itu tidak boleh dijawab dengan
 * melihat jumlah like, kerana organik diedar kepada peminat sedia ada manakala
 * iklan diedar kepada orang asing. Sebab itu skrin ini tetap membuat split
 * test, bukan terus menolak posting paling popular.
 */
#[Layout('layouts.app')]
#[Title('Guna posting sedia ada')]
class ExistingPosts extends Component
{
    /** @var array<int, string> */
    public array $selected = [];

    /** @var array<int, string> */
    public array $regionKeys = [];

    public int $budgetRm = 37;

    /** @var array<int, array{key:string,name:string}> */
    public array $regions = [];

    /** @var array<int, array<string, mixed>> */
    public array $posts = [];

    public ?string $cursor = null;

    public ?string $nextCursor = null;

    public ?string $loadError = null;

    public ?string $regionError = null;

    public ?string $error = null;

    public bool $working = false;

    public function mount(MetaAdsService $meta, PagePostService $pagePosts): void
    {
        $this->budgetRm = intdiv((int) config('dynoads.budget.default_daily_sen'), 100);

        try {
            $this->regions = $meta->searchRegions();
        } catch (Throwable $e) {
            $this->regions = [];
            $this->regionError = $this->safeReason($e);
        }

        $this->loadPosts($pagePosts);
    }

    public function loadPosts(PagePostService $pagePosts): void
    {
        try {
            $page = $pagePosts->listPosts(after: $this->cursor);

            $this->posts = [...$this->posts, ...$page['posts']];
            $this->nextCursor = $page['next_cursor'];
            $this->loadError = null;
        } catch (Throwable $e) {
            $this->loadError = $this->safeReason($e);
        }
    }

    public function loadMore(PagePostService $pagePosts): void
    {
        if (blank($this->nextCursor)) {
            return;
        }

        $this->cursor = $this->nextCursor;
        $this->loadPosts($pagePosts);
    }

    public function toggle(string $postId): void
    {
        if (in_array($postId, $this->selected, true)) {
            $this->selected = array_values(array_diff($this->selected, [$postId]));
            $this->resetValidation('selected');

            return;
        }

        if (count($this->selected) >= $this->maxSelection()) {
            $this->addError('selected', "Pilih paling banyak {$this->maxSelection()} posting. Lebih dari tu, bajet berpecah terlalu nipis untuk sesiapa menang.");

            return;
        }

        $this->selected[] = $postId;
        $this->resetValidation('selected');
    }

    public function isSelected(string $postId): bool
    {
        return in_array($postId, $this->selected, true);
    }

    public function minSelection(): int
    {
        return (int) config('dynoads.page_posts.min_selection');
    }

    public function maxSelection(): int
    {
        return (int) config('dynoads.page_posts.max_selection');
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

    public function totalDailyRm(): int
    {
        return max(count($this->selected), 1) * $this->budgetRm;
    }

    /**
     * Buat campaign PAUSED untuk setiap posting yang dipilih.
     *
     * Tiada duit dibelanjakan di sini — peraturan mutlak #3 dan #6. Skrin Run
     * yang menjadi klik pengesahan yang jelas itu.
     */
    public function launch(ImageProcessor $processor, AdLauncher $launcher)
    {
        $min = $this->minSelection();
        $max = $this->maxSelection();

        if (count($this->selected) < $min) {
            $this->addError('selected', "Pilih sekurang-kurangnya {$min} posting supaya ada yang boleh dibandingkan.");

            return null;
        }

        $minRm = intdiv((int) config('dynoads.budget.min_daily_sen'), 100);
        $maxRm = intdiv((int) config('dynoads.budget.max_daily_sen'), 100);

        if ($this->budgetRm < $minRm || $this->budgetRm > $maxRm) {
            $this->addError('budgetRm', "Bajet harian kena antara RM{$minRm} dan RM{$maxRm}.");

            return null;
        }

        $chosen = collect($this->posts)
            ->whereIn('id', $this->selected)
            ->take($max)
            ->values();

        if ($chosen->count() < $min) {
            $this->addError('selected', 'Posting yang dipilih tiada dalam senarai lagi. Muat semula dan cuba lagi.');

            return null;
        }

        $this->working = true;
        $this->error = null;

        $set = AdSet::create([
            'name' => 'Posting sedia ada — '.now()->format('d M Y'),
            // Posting sudah membawa teksnya sendiri, jadi tiada pain point baharu
            // untuk ditulis. Rekod ini kekal jujur tentang dari mana ia datang.
            'problem' => 'Guna posting sedia ada — teks iklan datang dari posting itu sendiri.',
            'offer' => (string) ($chosen->first()['excerpt'] ?: 'Posting Page sedia ada'),
            'phone' => (string) config('dynoads.meta.wa_phone'),
            'region_keys' => $this->regionKeys,
            'region_names' => $this->regionNames(),
            'daily_budget_sen' => $this->budgetRm * 100,
            'status' => 'draft',
        ]);

        $disk = Storage::disk('public');
        $position = 0;

        try {
            foreach ($chosen as $post) {
                $relative = "dynoads/{$set->id}/".(++$position).'.jpg';
                $disk->makeDirectory(dirname($relative));

                // Salinan tempatan dipakai untuk pratonton di skrin Run dan
                // Dashboard, dan menjadi creative kalau Meta menolak butang
                // WhatsApp di atas posting asal. Posting asal tidak disentuh.
                $processor->squareCrop($this->fetchPicture((string) $post['full_picture']), $disk->path($relative));

                AdVariant::create([
                    'ad_set_id' => $set->id,
                    'position' => $position,
                    'source_type' => 'existing_post',
                    'source_post_id' => (string) $post['id'],
                    'image_path' => $relative,
                    'caption' => (string) ($post['message'] ?: $post['excerpt']),
                ]);
            }

            $launcher->createAll($set->load('variants'));
        } catch (MetaApiException $e) {
            $this->working = false;
            $this->error = $e->forHuman();

            return null;
        } catch (Throwable $e) {
            $this->working = false;
            $this->error = $this->safeReason($e);

            return null;
        }

        return $this->redirectRoute('ad-sets.run', ['adSet' => $set], navigate: true);
    }

    /** Muat turun gambar posting ke fail sementara untuk dipotong jadi 1080×1080. */
    protected function fetchPicture(string $url): string
    {
        $response = Http::timeout((int) config('dynoads.meta.timeout'))->get($url);

        if ($response->failed()) {
            throw new \RuntimeException('Gagal muat turun gambar posting dari Facebook. Cuba lagi sekejap.');
        }

        $temp = tempnam(sys_get_temp_dir(), 'dynoads_post_');
        file_put_contents($temp, $response->body());

        return $temp;
    }

    /** @return array<int, string> */
    protected function regionNames(): array
    {
        return collect($this->regions)
            ->whereIn('key', $this->regionKeys)
            ->pluck('name')
            ->all();
    }

    /**
     * Peraturan mutlak #8: mesej pengecualian HTTP membawa URL penuh, dan URL
     * Graph API mengandungi ?access_token=. Jadi kita tidak pernah memaparkan
     * mesej mentah.
     */
    private function safeReason(Throwable $e): string
    {
        return match (true) {
            $e instanceof MetaApiException => $e->forHuman(),
            $e instanceof ConnectionException => 'Tidak dapat hubungi Meta dari pelayan ini.',
            default => 'Panggilan ke Meta gagal ('.class_basename($e).').',
        };
    }

    public function render()
    {
        return view('livewire.ad-sets.existing-posts');
    }
}
