<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    public const STATUS_TENTATIVE = 'tentative';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_WAITLIST = 'waitlist';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_CHECKED_OUT = 'checked_out';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';
    public const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'tenant_id', 'property_id',
        'reservation_number', 'confirmation_number',
        'group_reservation_id', 'is_group_master',
        'guest_id', 'company_id',
        'guest_name', 'guest_phone', 'guest_email',
        'source_type', 'source_name', 'ota_booking_id', 'ota_channel_code',
        'market_segment', 'business_source',
        'arrival_date', 'departure_date', 'arrival_time', 'departure_time', 'nights',
        'rooms_count', 'adults', 'children', 'infants',
        'status', 'status_changed_at', 'status_changed_by',
        'room_revenue', 'total_tax', 'total_discount', 'total_amount',
        'paid_amount', 'balance_amount', 'currency',
        'advance_amount', 'advance_due_date', 'advance_received',
        'cancelled_at', 'cancelled_by', 'cancellation_reason', 'cancellation_charge',
        'special_requests', 'internal_notes',
        'is_vip', 'is_complimentary', 'is_house_use',
        'billing_to', 'billing_instructions',
        'created_by', 'updated_by',
        'device_id', 'sync_version', 'synced_at',
    ];

    protected $casts = [
        'arrival_date' => 'date',
        'departure_date' => 'date',
        'advance_due_date' => 'date',
        'arrival_time' => 'string',
        'departure_time' => 'string',
        'status_changed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'synced_at' => 'datetime',
        'nights' => 'integer',
        'rooms_count' => 'integer',
        'adults' => 'integer',
        'children' => 'integer',
        'infants' => 'integer',
        'sync_version' => 'integer',
        'room_revenue' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
        'advance_amount' => 'decimal:2',
        'cancellation_charge' => 'decimal:2',
        'is_group_master' => 'boolean',
        'advance_received' => 'boolean',
        'is_vip' => 'boolean',
        'is_complimentary' => 'boolean',
        'is_house_use' => 'boolean',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(ReservationRoom::class);
    }

    public function nights(): HasMany
    {
        return $this->hasMany(ReservationRoomNight::class);
    }

    public function folios(): HasMany
    {
        return $this->hasMany(Folio::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_CONFIRMED,
            self::STATUS_TENTATIVE,
            self::STATUS_CHECKED_IN,
        ]);
    }

    public function isCheckedIn(): bool
    {
        return $this->status === self::STATUS_CHECKED_IN;
    }

    public function canCheckIn(): bool
    {
        return in_array($this->status, [self::STATUS_CONFIRMED, self::STATUS_TENTATIVE]);
    }

    public function canCheckOut(): bool
    {
        return $this->status === self::STATUS_CHECKED_IN;
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_CONFIRMED, self::STATUS_TENTATIVE, self::STATUS_WAITLIST]);
    }

    /* Scopes */
    public function scopeArrivingOn(Builder $q, $date): Builder
    {
        return $q->whereDate('arrival_date', $date);
    }

    public function scopeDepartingOn(Builder $q, $date): Builder
    {
        return $q->whereDate('departure_date', $date);
    }

    public function scopeInHouseOn(Builder $q, $date): Builder
    {
        return $q->where('arrival_date', '<=', $date)
            ->where('departure_date', '>', $date)
            ->where('status', self::STATUS_CHECKED_IN);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', [
            self::STATUS_CONFIRMED, self::STATUS_TENTATIVE, self::STATUS_CHECKED_IN,
        ]);
    }
}
