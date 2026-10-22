<?php

namespace Database\Factories;

use App\Models\AdSet;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AdSet> */
class AdSetFactory extends Factory
{
    protected $model = AdSet::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => 'Sistem POS fullset',
            'problem' => 'kira duit lambat waktu peak hour',
            'offer' => 'sistem POS fullset, pasang di kedai',
            'phone' => '60187922844',
            'region_keys' => [],
            'region_names' => [],
            'daily_budget_sen' => 3700,
            'status' => 'draft',
        ];
    }
}
