<?php

namespace App\Models\Banquet;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Company;
use App\Models\Guest;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BanquetHall extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'banquet_halls';
    protected $guarded = ['id'];
    protected $casts = [
        'area_sqft' => 'integer',
        'theatre_capacity' => 'integer',
        'classroom_capacity' => 'integer',
        'cluster_capacity' => 'integer',
        'banquet_capacity' => 'integer',
        'hourly_rate' => 'decimal:2',
        'half_day_rate' => 'decimal:2',
        'full_day_rate' => 'decimal:2',
        'amenities' => 'array',
        'is_active' => 'boolean',
    ];

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function bookings(): HasMany { return $this->hasMany(BanquetBooking::class, 'hall_id'); }

    public function isAvailableOn(\DateTimeInterface $date, ?string $startTime = null, ?string $endTime = null): bool
    {
        $q = BanquetBooking::where('hall_id', $this->id)
            ->whereDate('event_date', $date)
            ->whereIn('status', ['tentative', 'confirmed', 'completed']);

        if ($startTime && $endTime) {
            $q->where(function ($qq) use ($startTime, $endTime) {
                $qq->whereBetween('event_start_time', [$startTime, $endTime])
                   ->orWhereBetween('event_end_time', [$startTime, $endTime])
                   ->orWhere(function ($qqq) use ($startTime, $endTime) {
                       $qqq->where('event_start_time', '<=', $startTime)
                           ->where('event_end_time', '>=', $endTime);
                   });
            });
        }

        return ! $q->exists();
    }
}
