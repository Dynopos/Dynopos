<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosterJob extends Model
{
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'template', 'data', 'background_source', 'background_mood', 'background_path',
        'product_path', 'cutout_path', 'output_path', 'cache_key', 'status', 'last_error',
    ];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function isDone(): bool
    {
        return $this->status === 'done' && filled($this->output_path);
    }
}
