<?php

namespace Database\Factories;

use App\Models\FbConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FbConnection>
 */
class FbConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token' => 'TOKEN-'.fake()->unique()->lexify('??????????????????????????????'),
            'ad_account_id' => 'act_'.fake()->unique()->numerify('##########'),
            'page_id' => fake()->unique()->numerify('###############'),
            'page_name' => fake()->company(),
            'wa_phone' => '60'.fake()->unique()->numerify('1#########'),
            'status' => 'active',
            'verified_at' => now(),
        ];
    }

    public function expiring(int $days = 3): static
    {
        return $this->state(fn () => ['expires_at' => now()->addDays($days)]);
    }
}
