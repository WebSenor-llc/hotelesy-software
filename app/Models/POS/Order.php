<?php

namespace App\Models\POS;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Folio;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'pos_orders';
    protected $guarded = ['id'];

    public const STATUS_OPEN = 'open';
    public const STATUS_SENT = 'sent_to_kitchen';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_READY = 'ready';
    public const STATUS_SERVED = 'served';
    public const STATUS_BILLED = 'billed';
    public const STATUS_SETTLED = 'settled';
    public const STATUS_VOIDED = 'voided';

    public const TYPE_DINE_IN = 'dine_in';
    public const TYPE_ROOM_SERVICE = 'room_service';
    public const TYPE_TAKEAWAY = 'takeaway';
    public const TYPE_DELIVERY = 'delivery';
    public const TYPE_BANQUET = 'banquet';

    protected $casts = [
        'opened_at' => 'datetime',
        'kot_printed_at' => 'datetime',
        'served_at' => 'datetime',
        'billed_at' => 'datetime',
        'settled_at' => 'datetime',
        'covers' => 'integer',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'round_off' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'tax_breakdown' => 'array',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(PosTable::class, 'table_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function activeItems(): HasMany
    {
        return $this->items()->where('is_voided', false);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(User::class, 'server_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(\App\Models\KDS\KdsTicket::class, 'order_id');
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, [self::STATUS_SETTLED, self::STATUS_VOIDED], true);
    }

    public function isSettled(): bool
    {
        return $this->status === self::STATUS_SETTLED;
    }

    public function canVoid(): bool
    {
        return $this->status !== self::STATUS_SETTLED;
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNotIn('status', [self::STATUS_SETTLED, self::STATUS_VOIDED]);
    }

    public function scopeForReservation(Builder $q, int $reservationId): Builder
    {
        return $q->where('reservation_id', $reservationId);
    }
}
