<?php

namespace App\Models\Compliance;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Folio;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EInvoice extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'e_invoices';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_GENERATED = 'generated';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id', 'property_id', 'folio_id',
        'invoice_number', 'irn', 'ack_number', 'ack_date',
        'qr_code_data', 'json_payload', 'status',
        'error_message',
        'generated_at', 'cancelled_at', 'cancellation_reason',
    ];

    protected $casts = [
        'json_payload' => 'array',
        'ack_date' => 'datetime',
        'generated_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function qrImageUrl(): string
    {
        $data = $this->irn ?: ($this->qr_code_data ?: $this->invoice_number);
        return 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . urlencode($data);
    }
}
