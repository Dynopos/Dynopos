<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Business> */
class BusinessFactory extends Factory
{
    protected $model = Business::class;

    /** Lalai: analisis sudah disahkan, supaya test caption terus berjalan. */
    public function definition(): array
    {
        return [
            'name' => 'DynoPOS',
            'description' => 'Sistem POS fullset untuk kedai runcit dan kafe, pasang di kedai.',
            'analysis' => [
                'field' => 'Sistem POS untuk kedai runcit dan kafe',
                'summary' => 'Jual dan pasang sistem POS fullset di kedai.',
                'offers' => ['sistem POS fullset'],
                'customers' => ['kedai runcit', 'kafe'],
                'pains' => ['kira duit lambat'],
                'angles' => ['jimat masa tutup kedai'],
                'avoid' => ['pos laju'],
                'confidence' => 'tinggi',
                'question' => '',
                'source' => 'ai',
            ],
            'analysis_confirmed_at' => now(),
            'analysis_count' => 1,
        ];
    }

    public function unanalysed(): static
    {
        return $this->state(fn () => [
            'analysis' => null,
            'analysis_confirmed_at' => null,
            'analysis_count' => 0,
        ]);
    }

    public function unconfirmed(): static
    {
        return $this->state(fn () => ['analysis_confirmed_at' => null]);
    }
}
