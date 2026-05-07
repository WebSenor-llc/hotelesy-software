<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'code', 'name', 'legal_name',
        'address', 'city', 'state', 'country', 'postal_code',
        'latitude', 'longitude', 'phone', 'email', 'website',
        'gst_number', 'pan_number', 'fssai_number', 'liquor_license',
        'einvoice_required',
        'check_in_time', 'check_out_time', 'night_audit_time',
        'current_business_date', 'night_audit_locked',
        'currency', 'timezone', 'total_rooms', 'floors',
        'status', 'settings',
    ];

    protected $casts = [
        'check_in_time' => 'string',
        'check_out_time' => 'string',
        'night_audit_time' => 'string',
        'current_business_date' => 'date',
        'night_audit_locked' => 'boolean',
        'einvoice_required' => 'boolean',
        'total_rooms' => 'integer',
        'floors' => 'integer',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'settings' => 'array',
    ];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class);
    }

    public function ratePlans(): HasMany
    {
        return $this->hasMany(RatePlan::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'property_user');
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class);
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'property_modules')
            ->withPivot('is_enabled', 'settings')
            ->withTimestamps();
    }

    /**
     * The property's "today" - respects business date locked by night audit.
     */
    public function businessDate(): \Carbon\Carbon
    {
        return $this->current_business_date
            ? \Carbon\Carbon::parse($this->current_business_date)
            : now($this->timezone)->startOfDay();
    }
}
