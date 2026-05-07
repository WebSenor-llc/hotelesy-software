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

class GstReturn extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'accounts_gst_returns';
    protected $guarded = ['id'];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_COMPUTED = 'computed';
    public const STATUS_FILED = 'filed';
    public const STATUS_AMENDED = 'amended';

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'taxable_value' => 'decimal:2',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'igst_amount' => 'decimal:2',
        'cess_amount' => 'decimal:2',
        'itc_cgst' => 'decimal:2',
        'itc_sgst' => 'decimal:2',
        'itc_igst' => 'decimal:2',
        'detailed_data' => 'array',
        'filed_at' => 'datetime',
    ];

    public function netLiability(): float
    {
        $total = (float) $this->cgst_amount + (float) $this->sgst_amount + (float) $this->igst_amount;
        $itc = (float) $this->itc_cgst + (float) $this->itc_sgst + (float) $this->itc_igst;
        return max(0, $total - $itc);
    }
}
