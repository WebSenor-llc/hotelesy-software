<?php

namespace App\Models\Amenities;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Folio;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmenityOrder extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'amenity_orders';
    protected $guarded = ['id'];
    protected $casts = [
        'service_date' => 'date',
        'service_time' => 'string',
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'fulfilled_at' => 'datetime',
    ];

    public function amenity(): BelongsTo { return $this->belongsTo(Amenity::class); }
    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function folio(): BelongsTo { return $this->belongsTo(Folio::class); }
}
