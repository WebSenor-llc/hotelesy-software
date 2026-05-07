<?php

namespace App\Models\Amenities;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Amenity extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'amenities';
    protected $guarded = ['id'];

    public const PRICING_PER_STAY = 'per_stay';
    public const PRICING_PER_NIGHT = 'per_night';
    public const PRICING_PER_PERSON = 'per_person';
    public const PRICING_PER_PERSON_PER_NIGHT = 'per_person_per_night';
    public const PRICING_FLAT = 'flat';

    protected $casts = [
        'price' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'image_urls' => 'array',
        'metadata' => 'array',
        'available_at_booking' => 'boolean',
        'available_at_checkin' => 'boolean',
        'available_in_stay' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(AmenityOrder::class, 'amenity_id');
    }

    /**
     * Compute total amount for the given stay parameters.
     */
    public function computeTotal(int $nights, int $persons = 1, int $quantity = 1): float
    {
        $base = match ($this->pricing_type) {
            self::PRICING_PER_STAY => $this->price * $quantity,
            self::PRICING_PER_NIGHT => $this->price * $nights * $quantity,
            self::PRICING_PER_PERSON => $this->price * $persons * $quantity,
            self::PRICING_PER_PERSON_PER_NIGHT => $this->price * $persons * $nights * $quantity,
            self::PRICING_FLAT => $this->price,
            default => $this->price * $quantity,
        };
        return round($base, 2);
    }
}
