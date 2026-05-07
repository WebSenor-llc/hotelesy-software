<?php

namespace App\Models\Accounts;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'accounts_vouchers';
    protected $guarded = ['id'];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_REVERSED = 'reversed';

    protected $casts = [
        'voucher_date' => 'date',
        'business_date' => 'date',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function voucherType(): BelongsTo { return $this->belongsTo(VoucherType::class); }
    public function entries(): HasMany { return $this->hasMany(VoucherEntry::class); }
    public function postedBy(): BelongsTo { return $this->belongsTo(User::class, 'posted_by'); }

    public function isBalanced(): bool
    {
        return abs((float) $this->total_debit - (float) $this->total_credit) < 0.01;
    }

    public function scopePosted(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_POSTED);
    }
}
