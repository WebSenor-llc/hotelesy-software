<?php

namespace App\Models\Reviews;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewSource extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'review_sources';
    protected $guarded = ['id'];
    protected $casts = [
        'credentials' => 'encrypted:array',
        'auto_fetch' => 'boolean',
        'is_active' => 'boolean',
        'last_fetched_at' => 'datetime',
    ];

    public const SOURCE_GOOGLE = 'google';
    public const SOURCE_TRIPADVISOR = 'tripadvisor';
    public const SOURCE_BOOKING_COM = 'booking_com';
    public const SOURCE_MMT = 'mmt';

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'source_id');
    }
}
