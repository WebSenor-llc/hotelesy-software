<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * MarketingLead — pre-customer lead captured from public landing page.
 * Lives outside tenancy (no tenant_id) until conversion.
 */
class MarketingLead extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'marketing_leads';

    protected $guarded = ['id'];

    protected $casts = [
        'rooms_count'     => 'integer',
        'contacted_at'    => 'datetime',
        'next_followup_at'=> 'datetime',
    ];

    public const STATUSES = [
        'new' => 'New',
        'contacted' => 'Contacted',
        'qualified' => 'Qualified',
        'demo_scheduled' => 'Demo Scheduled',
        'proposal_sent' => 'Proposal Sent',
        'won' => 'Won',
        'lost' => 'Lost',
        'unqualified' => 'Unqualified',
    ];

    public function convertedTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'converted_tenant_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SubscriptionTransaction::class, 'lead_id');
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'new' => 'bg-sky-100 text-sky-700',
            'contacted' => 'bg-amber-100 text-amber-700',
            'qualified' => 'bg-violet-100 text-violet-700',
            'demo_scheduled' => 'bg-indigo-100 text-indigo-700',
            'proposal_sent' => 'bg-blue-100 text-blue-700',
            'won' => 'bg-emerald-100 text-emerald-700',
            'lost' => 'bg-rose-100 text-rose-700',
            'unqualified' => 'bg-slate-100 text-slate-600',
            default => 'bg-slate-100 text-slate-600',
        };
    }
}
