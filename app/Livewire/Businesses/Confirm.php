<?php

namespace App\Livewire\Businesses;

use App\Models\AdSet;
use App\Models\Business;
use App\Services\BusinessAnalyst;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

/**
 * "Betul ke ni bisnes anda?" — satu skrin, satu keputusan.
 *
 * AI Amir menerangkan semula bisnes. Peniaga sahkan, betulkan medan sendiri,
 * atau minta analisis semula dengan pembetulan. Caption hanya ditulis selepas
 * butang sahkan ditekan.
 */
#[Layout('layouts.app')]
#[Title('Sahkan bisnes anda')]
class Confirm extends Component
{
    public Business $business;

    /** Set iklan yang menunggu caption, kalau datang dari /buat. */
    public ?int $setId = null;

    public string $field = '';

    public string $summary = '';

    public string $avoid = '';

    public string $correction = '';

    public ?string $error = null;

    public function mount(Business $business, BusinessAnalyst $analyst): void
    {
        $this->business = $business;

        // Global scope: set milik peniaga lain tidak dijumpai.
        $this->setId = AdSet::query()->whereKey(request()->integer('set'))->value('id');

        if ($business->analysis === null) {
            $this->run($analyst);
        }

        $this->fillFields();
    }

    public function reanalyse(BusinessAnalyst $analyst): void
    {
        $this->validate(
            ['correction' => 'required|string|min:5|max:600'],
            ['correction.required' => 'Tulis apa yang AI silap dulu.', 'correction.min' => 'Tulis lebih sikit apa yang silap.'],
        );

        $this->run($analyst, $this->correction);
        $this->correction = '';
        $this->fillFields();
    }

    public function confirm(BusinessAnalyst $analyst)
    {
        $this->validate(
            ['field' => 'required|string|max:200', 'summary' => 'nullable|string|max:600', 'avoid' => 'nullable|string|max:600'],
            ['field.required' => 'Isi bidang bisnes dulu.'],
        );

        $analyst->confirm($this->business, [
            'field' => $this->field,
            'summary' => $this->summary,
            'avoid' => $this->avoid,
        ]);

        return $this->setId !== null
            ? $this->redirectRoute('ad-sets.review', ['adSet' => $this->setId], navigate: true)
            : $this->redirectRoute('ad-sets.create', navigate: true);
    }

    protected function run(BusinessAnalyst $analyst, ?string $correction = null): void
    {
        $this->error = null;

        try {
            $this->business = $analyst->analyse($this->business, $correction, $this->hint());
        } catch (RuntimeException $e) {
            $this->error = $e->getMessage();
        }
    }

    /** Masalah dan tawaran set iklan semasa membantu AI faham bisnes. */
    protected function hint(): ?string
    {
        $set = $this->setId === null ? null : AdSet::find($this->setId);

        return $set === null ? null : "Masalah pelanggan: {$set->problem}\nTawaran: {$set->offer}";
    }

    protected function fillFields(): void
    {
        $analysis = (array) $this->business->analysis;

        $this->field = (string) ($analysis['field'] ?? $this->business->name);
        $this->summary = (string) ($analysis['summary'] ?? $this->business->description);
        $this->avoid = implode(', ', $this->business->avoidList());
    }

    public function render(BusinessAnalyst $analyst)
    {
        return view('livewire.businesses.confirm', [
            'analysis' => (array) $this->business->analysis,
            'left' => $analyst->analysesLeft($this->business),
        ]);
    }
}
