<?php

namespace App\Livewire\AdSets;

use App\Exceptions\MetaApiException;
use App\Models\AdSet;
use App\Models\Business;
use App\Services\AdLauncher;
use App\Services\CaptionService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Semak sebelum lancar')]
class Review extends Component
{
    public AdSet $adSet;

    /** @var array<int, string> caption ikut id variant */
    public array $captions = [];

    public bool $working = false;

    public ?string $error = null;

    /** Diisi bila caption datang dari acuan asas, bukan dari Claude. */
    public ?string $fallbackReason = null;

    public function mount(AdSet $adSet, CaptionService $captions)
    {
        $this->adSet = $adSet->load('variants', 'business');

        $missing = $this->adSet->variants->whereNull('caption');

        // Caption hanya untuk bisnes yang disahkan (peraturan mutlak #14).
        if ($missing->isNotEmpty() && $this->adSet->business && ! $this->adSet->business->hasConfirmedAnalysis()) {
            return $this->toBusiness();
        }

        if ($missing->isNotEmpty() && $this->adSet->business) {
            $set = $captions->generate(
                $this->adSet->business,
                $this->adSet->problem,
                $this->adSet->offer,
                $missing->count()
            );

            $this->fallbackReason = $set->fromFallback ? $set->reason : null;

            foreach ($missing->values() as $i => $variant) {
                $variant->update(['caption' => $set->captions[$i] ?? $set->captions[0]]);
            }

            $this->adSet->load('variants');
        }

        foreach ($this->adSet->variants as $variant) {
            $this->captions[$variant->id] = (string) $variant->caption;
        }

        return null;
    }

    public function regenerate(CaptionService $captions)
    {
        $business = $this->adSet->business;

        if ($business === null) {
            $this->error = 'Pilih bisnes untuk set iklan ni dulu.';

            return null;
        }

        if (! $business->hasConfirmedAnalysis()) {
            return $this->toBusiness();
        }

        $set = $captions->generate(
            $business,
            $this->adSet->problem,
            $this->adSet->offer,
            $this->adSet->variants->count()
        );

        $this->fallbackReason = $set->fromFallback ? $set->reason : null;

        foreach ($this->adSet->variants->values() as $i => $variant) {
            $variant->update(['caption' => $set->captions[$i] ?? $set->captions[0]]);
            $this->captions[$variant->id] = (string) $variant->caption;
        }

        $this->adSet->load('variants');

        return null;
    }

    /**
     * Set iklan lama (sebelum analisis bisnes) dipautkan kepada bisnes yang
     * sudah disahkan, supaya "Minta caption lain" boleh berjalan.
     */
    public function attachBusiness(int $id): void
    {
        $business = Business::find($id);

        if ($business === null || ! $business->hasConfirmedAnalysis()) {
            $this->error = 'Bisnes tu tak dijumpai atau belum disahkan.';

            return;
        }

        $this->adSet->update(['business_id' => $business->id]);
        $this->adSet->load('business');
        $this->error = null;
    }

    protected function toBusiness()
    {
        return $this->redirectRoute('businesses.confirm', [
            'business' => $this->adSet->business,
            'set' => $this->adSet->id,
        ], navigate: true);
    }

    /** Approve → campaign dibuat di Meta, semuanya PAUSED. Tiada duit lagi. */
    public function approve(AdLauncher $launcher, CaptionService $captions)
    {
        $this->error = null;
        $this->working = true;

        foreach ($this->adSet->variants as $variant) {
            $caption = trim((string) ($this->captions[$variant->id] ?? ''));

            if ($caption === '') {
                $this->working = false;
                $this->error = 'Setiap iklan kena ada caption.';

                return null;
            }

            if ($captions->hasForbiddenWord($caption)) {
                $this->working = false;
                $this->error = 'Ada perkataan yang Meta biasa tolak dalam caption. Ubah dulu.';

                return null;
            }

            if ($terms = $this->adSet->business?->offFieldTerms($caption)) {
                $this->working = false;
                $this->error = 'Caption iklan #'.$variant->position.' sebut "'.implode('", "', $terms)
                    .'" — itu bukan bisnes anda. Ubah dulu.';

                return null;
            }

            $variant->update(['caption' => $caption]);
        }

        try {
            $launcher->createAll($this->adSet->refresh());
        } catch (MetaApiException $e) {
            $this->working = false;
            $this->error = $e->forHuman();

            return null;
        } finally {
            $this->adSet->refresh()->load('variants');
        }

        $this->working = false;

        return $this->redirectRoute('ad-sets.run', ['adSet' => $this->adSet], navigate: true);
    }

    /** @return array<int, array<int, string>> dakwaan berisiko ikut id variant */
    public function riskyClaims(CaptionService $captions): array
    {
        return collect($this->captions)
            ->map(fn (string $caption) => $captions->riskyClaims($caption))
            ->filter()
            ->all();
    }

    public function render(CaptionService $captions)
    {
        return view('livewire.ad-sets.review', [
            'risiko' => $this->riskyClaims($captions),
            'confirmedBusinesses' => $this->adSet->business
                ? collect()
                : Business::query()->whereNotNull('analysis_confirmed_at')->latest('updated_at')->get(),
        ]);
    }
}
