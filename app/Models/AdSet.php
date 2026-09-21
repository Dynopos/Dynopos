<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdSet extends Model
{
    use BelongsToUser, HasFactory;

    /**
     * user_id SENGAJA tiada di sini. Ia ditetapkan oleh BelongsToUser
     * daripada user yang log masuk, bukan daripada input borang — supaya tiada
     * permintaan boleh mengaku data ini milik orang lain.
     */
    protected $fillable = [
        'name', 'problem', 'offer', 'phone',
        'region_keys', 'region_names', 'daily_budget_sen', 'status',
    ];

    protected function casts(): array
    {
        return [
            'daily_budget_sen' => 'integer',
            'region_keys' => 'array',
            'region_names' => 'array',
        ];
    }

    public function variants(): HasMany
    {
        return $this->hasMany(AdVariant::class)->orderBy('position');
    }

    public function autoActions(): HasMany
    {
        return $this->hasMany(AutoAction::class)->latest('id');
    }

    /** Jumlah bajet sehari untuk keseluruhan set — satu gambar satu campaign. */
    public function totalDailyBudgetSen(): int
    {
        return $this->daily_budget_sen * $this->variants()->count();
    }

    public function regionLabel(): string
    {
        $names = array_filter((array) $this->region_names);

        return $names === [] ? 'Seluruh Malaysia' : implode(', ', $names);
    }

    /** @return array<int, string> */
    public function regionKeyList(): array
    {
        return array_values(array_filter((array) $this->region_keys));
    }
}
