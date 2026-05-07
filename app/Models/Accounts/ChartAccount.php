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

class ChartAccount extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'accounts_chart';
    protected $guarded = ['id'];

    public const TYPE_ASSET = 'asset';
    public const TYPE_LIABILITY = 'liability';
    public const TYPE_EQUITY = 'equity';
    public const TYPE_INCOME = 'income';
    public const TYPE_EXPENSE = 'expense';

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'opening_balance' => 'decimal:2',
        'opening_balance_date' => 'date',
    ];

    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }

    /**
     * Compute current balance from voucher entries.
     * Asset/Expense: Dr - Cr (positive = balance owed to us / spent)
     * Liability/Equity/Income: Cr - Dr (positive = balance we owe / earned)
     */
    public function currentBalance(?\DateTimeInterface $asOf = null): float
    {
        $q = VoucherEntry::query()
            ->join('accounts_vouchers', 'accounts_voucher_entries.voucher_id', '=', 'accounts_vouchers.id')
            ->where('accounts_voucher_entries.account_id', $this->id)
            ->where('accounts_vouchers.status', 'posted');

        if ($asOf) {
            $q->whereDate('accounts_vouchers.voucher_date', '<=', $asOf);
        }

        $debits = (float) (clone $q)->where('side', 'debit')->sum('amount');
        $credits = (float) (clone $q)->where('side', 'credit')->sum('amount');
        $opening = (float) $this->opening_balance;

        return match ($this->type) {
            self::TYPE_ASSET, self::TYPE_EXPENSE => $opening + $debits - $credits,
            self::TYPE_LIABILITY, self::TYPE_EQUITY, self::TYPE_INCOME => $opening + $credits - $debits,
            default => 0.0,
        };
    }
}
