<?php

namespace App\Models\Reviews;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'reviews';
    protected $guarded = ['id'];

    public const SENTIMENT_POSITIVE = 'positive';
    public const SENTIMENT_NEUTRAL = 'neutral';
    public const SENTIMENT_NEGATIVE = 'negative';

    protected $casts = [
        'stay_date' => 'date',
        'review_date' => 'date',
        'rating' => 'decimal:1',
        'original_rating' => 'decimal:1',
        'aspect_ratings' => 'array',
        'raw_payload' => 'array',
        'responded_at' => 'datetime',
        'is_visible' => 'boolean',
        'is_flagged' => 'boolean',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ReviewSource::class, 'source_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function isResponded(): bool
    {
        return ! empty($this->response_text);
    }

    public function scopeRequiringResponse(Builder $q): Builder
    {
        return $q->whereNull('response_text')->where('is_visible', true);
    }

    public function scopeNegative(Builder $q): Builder
    {
        return $q->where(fn($qq) => $qq->where('rating', '<=', 3.0)
            ->orWhere('sentiment', self::SENTIMENT_NEGATIVE));
    }
}
