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

class VoucherEntry extends Model
{
    use HasFactory;

    protected $table = 'accounts_voucher_entries';
    protected $guarded = ['id'];
    protected $casts = ['amount' => 'decimal:2'];

    public function voucher(): BelongsTo { return $this->belongsTo(Voucher::class); }
    public function account(): BelongsTo { return $this->belongsTo(ChartAccount::class, 'account_id'); }
}
