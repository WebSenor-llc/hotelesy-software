<?php

namespace App\Models\KDS;

use App\Models\Concerns\BelongsToTenant;
use App\Models\POS\OrderItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KdsTicketItem extends Model
{
    use HasFactory;

    protected $table = 'kds_ticket_items';
    protected $guarded = ['id'];
    protected $casts = [
        'quantity' => 'decimal:2',
        'modifiers' => 'array',
        'ready_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(KdsTicket::class, 'ticket_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}
