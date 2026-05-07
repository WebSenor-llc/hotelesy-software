<?php

namespace App\Models\Compliance;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Guest;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormCSubmission extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'form_c_submissions';

    protected $fillable = [
        'tenant_id', 'property_id', 'guest_id', 'reservation_id',
        'form_number',
        'generated_at', 'generated_by',
        'submitted_at', 'submitted_by',
        'frro_acknowledgement_number', 'submission_method', 'notes',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
