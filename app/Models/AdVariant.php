<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'ad_set_id', 'position', 'source_type', 'media_type', 'poster_job_id', 'source_post_id',
        'image_path', 'video_path', 'caption',
        'meta_image_hash', 'meta_video_id', 'meta_thumbnail_url',
        'meta_campaign_id', 'meta_adset_id',
        'meta_creative_id', 'meta_ad_id', 'status', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function adSet(): BelongsTo
    {
        return $this->belongsTo(AdSet::class);
    }

    /**
     * Poster daripada enjin poster yang sudah dibuang.
     *
     * Jadual poster_jobs dan baris source_type=poster dikekalkan sebagai
     * sejarah: campaign itu betul-betul berjalan dan angkanya masih bermakna
     * untuk perbandingan. Tiada poster baharu boleh dibuat lagi.
     */
    public function posterJob(): BelongsTo
    {
        return $this->belongsTo(PosterJob::class);
    }

    public function isPoster(): bool
    {
        return $this->source_type === 'poster';
    }

    public function isVideo(): bool
    {
        return $this->media_type === 'video';
    }

    /** Fail sebenar untuk variant ini, tidak kira jenisnya. */
    public function mediaPath(): ?string
    {
        return $this->isVideo() ? $this->video_path : $this->image_path;
    }

    /**
     * Creative ini ialah posting Page yang peniaga sudah ada.
     *
     * Bezanya bukan kosmetik: variant begini tiada gambar untuk dimuat naik,
     * dan creative-nya dibina daripada object_story_id supaya engagement
     * terkumpul pada posting asal.
     */
    public function isFromExistingPost(): bool
    {
        return $this->source_type === 'existing_post';
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(MetricDaily::class);
    }

    /**
     * Peraturan mutlak #2: app ini hanya menyentuh campaign yang ia sendiri buat.
     * Campaign lain dalam akaun langsung tiada dalam jadual ad_variants, jadi
     * pemeriksaan ini memadai — dan setiap pause/run mesti melaluinya.
     */
    public function isOwnedByApp(): bool
    {
        return filled($this->meta_campaign_id) && $this->exists;
    }

    public function isLive(): bool
    {
        return $this->status === 'active';
    }

    public function spendSen(): int
    {
        return (int) $this->metrics()->sum('spend_sen');
    }

    public function leads(): int
    {
        return (int) $this->metrics()->sum('leads');
    }

    public function costPerLeadSen(): ?int
    {
        $leads = $this->leads();

        return $leads > 0 ? intdiv($this->spendSen(), $leads) : null;
    }
}
