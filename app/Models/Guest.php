<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guest extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'salutation', 'first_name', 'last_name',
        'email', 'phone', 'phone_alternate', 'dob', 'gender',
        'nationality', 'is_foreign_national',
        'id_type', 'id_number', 'id_issuing_country',
        'id_expiry', 'id_document_path', 'id_proof_files',
        'passport_number', 'passport_expiry',
        'visa_number', 'visa_expiry',
        'arrival_in_india', 'arrival_from_country', 'arrival_date_in_india',
        'next_destination', 'next_destination_country',
        'address', 'city', 'state', 'country', 'postal_code',
        'gst_number', 'company_name', 'company_id',
        'segment', 'loyalty_tier', 'loyalty_number',
        'total_visits', 'lifetime_spend', 'last_stay_date', 'first_stay_date',
        'preferences', 'notes',
        'is_blacklisted', 'blacklist_reason',
        'marketing_consent_email', 'marketing_consent_sms', 'marketing_consent_whatsapp',
    ];

    protected $casts = [
        'dob' => 'date',
        'id_expiry' => 'date',
        'passport_expiry' => 'date',
        'visa_expiry' => 'date',
        'arrival_in_india' => 'date',
        'arrival_date_in_india' => 'date',
        'last_stay_date' => 'date',
        'first_stay_date' => 'date',
        'total_visits' => 'integer',
        'lifetime_spend' => 'decimal:2',
        'is_blacklisted' => 'boolean',
        'is_foreign_national' => 'boolean',
        'marketing_consent_email' => 'boolean',
        'marketing_consent_sms' => 'boolean',
        'marketing_consent_whatsapp' => 'boolean',
        'preferences' => 'array',
        'id_proof_files' => 'array',
    ];

    protected $appends = ['display_name'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return trim(($this->salutation ? $this->salutation . ' ' : '') . $this->first_name . ' ' . ($this->last_name ?? ''));
    }
}
