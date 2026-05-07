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

class VoucherType extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'accounts_voucher_types';
    protected $guarded = ['id'];
    protected $casts = ['is_active' => 'boolean'];

    public function vouchers(): HasMany { return $this->hasMany(Voucher::class); }
}
