<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'code', 'name', 'legal_name',
        'gst_number', 'pan_number',
        'tds_applicable', 'tds_certificate_number',
        'billing_address', 'city', 'state', 'country', 'postal_code',
        'contact_person', 'contact_email', 'contact_phone',
        'is_credit_account', 'credit_limit', 'credit_days', 'current_outstanding',
        'has_corporate_rate', 'corporate_discount_percent',
        'is_active', 'notes',
    ];

    protected $casts = [
        'is_credit_account' => 'boolean',
        'credit_limit' => 'decimal:2',
        'credit_days' => 'integer',
        'current_outstanding' => 'decimal:2',
        'has_corporate_rate' => 'boolean',
        'corporate_discount_percent' => 'decimal:2',
        'tds_applicable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function availableCredit(): float
    {
        return max(0, (float) $this->credit_limit - (float) $this->current_outstanding);
    }
}
