<?php

namespace App\Models\CMS;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'cms_sites';
    protected $guarded = ['id'];
    protected $casts = [
        'theme_config' => 'array',
        'seo_meta' => 'array',
        'contact' => 'array',
        'social_links' => 'array',
        'domain_verified' => 'boolean',
        'booking_engine_enabled' => 'boolean',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class, 'site_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'site_id');
    }

    public function publishedPages(): HasMany
    {
        return $this->pages()->where('is_published', true)->orderBy('sort_order');
    }
}
