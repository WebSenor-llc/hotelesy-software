<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxRule extends Model
{
    use HasFactory, BelongsToTenant;

    protected $guarded = ['id'];

    protected $casts = [
        'total_rate'        => 'decimal:3',
        'cgst_rate'         => 'decimal:3',
        'sgst_rate'         => 'decimal:3',
        'igst_rate'         => 'decimal:3',
        'cess_rate'         => 'decimal:3',
        'room_tariff_min'   => 'decimal:2',
        'room_tariff_max'   => 'decimal:2',
        'applies_when_hotel_has_liquor_license' => 'boolean',
        'itc_available'     => 'boolean',
        'effective_from'    => 'date',
        'effective_to'      => 'date',
        'is_active'         => 'boolean',
    ];

    public const SCOPE_ROOM         = 'room';
    public const SCOPE_FNB_NO_ITC   = 'fnb_no_itc';
    public const SCOPE_FNB_WITH_ITC = 'fnb_with_itc';
    public const SCOPE_BANQUET      = 'banquet';
    public const SCOPE_SERVICE      = 'service';
    public const SCOPE_LIQUOR       = 'liquor';
    public const SCOPE_TOBACCO      = 'tobacco';
    public const SCOPE_OTHER        = 'other';
}
