<?php

namespace App\Services\Tax;

use App\Models\Folio;
use App\Models\Property;
use App\Models\TaxInvoice;
use App\Models\TaxInvoiceLine;
use App\Models\TaxRule;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * InvoiceBuilder — generates a government-compliant TaxInvoice from any
 * source (Folio, POS Order, Banquet Booking). Computes per-line taxes via
 * TaxEngine, applies place-of-supply logic, persists header + lines and
 * returns the saved TaxInvoice.
 */
class InvoiceBuilder
{
    public function __construct(private readonly TaxEngine $engine) {}

    /**
     * Build from a Folio. Reads charges from a folio_charges relation if
     * present, otherwise falls back to a single line for the folio total.
     */
    public function buildFromFolio(Folio $folio, ?int $issuedBy = null): TaxInvoice
    {
        $property = Property::findOrFail($folio->property_id);

        $supplierStateCode  = $this->resolveStateCode($property->state_code, $property->state);
        $recipientStateCode = $this->resolveStateCode(
            $folio->billing_state_code ?? $supplierStateCode,
            $folio->billing_state ?? $property->state
        );

        // Source lines — try folio_charges; fall back to single line
        $rawLines = $this->extractFolioLines($folio);

        // Recipient details
        $recipient = [
            'name'     => $folio->billing_name ?: ($folio->guest_name ?: 'Guest'),
            'address'  => $folio->billing_address,
            'gstin'    => $folio->billing_gst,
            'pan'      => null,
            'state'    => $folio->billing_state ?? $property->state,
            'state_code'=> $recipientStateCode,
            'email'    => $folio->billing_email,
            'phone'    => $folio->billing_phone,
        ];

        return $this->build(
            tenantId: $folio->tenant_id,
            property: $property,
            source: $folio,
            rawLines: $rawLines,
            recipient: $recipient,
            placeOfSupply: $property->state ?: 'Unknown',
            placeOfSupplyCode: $supplierStateCode,
            isInterState: TaxEngine::isInterState($supplierStateCode, $recipientStateCode),
            amountPaid: (float) ($folio->total_payments ?? 0),
            issuedBy: $issuedBy,
        );
    }

    /**
     * Build from a POS Order. POS bills always have place-of-supply = property
     * (over-the-counter F&B). Recipient is usually walk-in or in-room guest.
     */
    public function buildFromPosOrder(Model $order, ?int $issuedBy = null, array $recipientOverride = []): TaxInvoice
    {
        $property = Property::findOrFail($order->property_id);

        $rawLines = [];
        $items = method_exists($order, 'items') ? $order->items : ($order->items ?? null);
        if ($items) {
            foreach ($items as $i) {
                $rawLines[] = [
                    'description'    => $i->item_name ?? $i->name ?? 'Item',
                    'hsn_sac_code'   => $i->hsn_sac_code ?? null,
                    'item_type'      => 'fnb',
                    'is_alcohol'     => (bool) ($i->is_alcohol ?? false),
                    'is_tobacco'     => (bool) ($i->is_tobacco ?? false),
                    'quantity'       => (float) ($i->quantity ?? 1),
                    'unit'           => 'NOS',
                    'rate'           => (float) ($i->rate ?? $i->unit_price ?? 0),
                    'discount_pct'   => 0,
                    'discount_amount'=> (float) ($i->discount_amount ?? 0),
                    'gross_amount'   => (float) (($i->rate ?? $i->unit_price ?? 0) * ($i->quantity ?? 1)),
                    'scope_hint'     => 'fnb',
                ];
            }
        } else {
            // Single line fallback
            $rawLines[] = [
                'description'    => 'Restaurant bill — Order #' . ($order->order_number ?? $order->id),
                'item_type'      => 'fnb',
                'quantity'       => 1,
                'rate'            => (float) ($order->subtotal ?? $order->total_amount ?? 0),
                'gross_amount'    => (float) ($order->subtotal ?? $order->total_amount ?? 0),
                'scope_hint'      => 'fnb',
            ];
        }

        $recipient = array_merge([
            'name'  => 'Walk-in Guest',
            'address' => null,
            'gstin' => null,
            'state' => $property->state,
            'state_code' => $property->state_code,
        ], $recipientOverride);

        $supplierStateCode  = $this->resolveStateCode($property->state_code, $property->state);
        $recipientStateCode = $this->resolveStateCode($recipient['state_code'] ?? $supplierStateCode, $recipient['state'] ?? $property->state);

        return $this->build(
            tenantId: $order->tenant_id,
            property: $property,
            source: $order,
            rawLines: $rawLines,
            recipient: array_merge($recipient, ['state_code' => $recipientStateCode]),
            placeOfSupply: $property->state ?: 'Unknown',
            placeOfSupplyCode: $supplierStateCode,
            isInterState: TaxEngine::isInterState($supplierStateCode, $recipientStateCode),
            amountPaid: (float) ($order->amount_paid ?? 0),
            issuedBy: $issuedBy,
        );
    }

    /**
     * Build from a Banquet booking. Single line (or split by component).
     */
    public function buildFromBanquetBooking(Model $booking, ?int $issuedBy = null): TaxInvoice
    {
        $property = Property::findOrFail($booking->property_id);

        $components = [];
        foreach ([
            ['hall_rent','Hall rent'],
            ['food_amount','Food'],
            ['beverage_amount','Beverages'],
            ['decor_amount','Decor'],
            ['av_amount','Audio-video / Lighting'],
            ['other_amount','Other'],
        ] as [$col, $label]) {
            $val = (float) ($booking->{$col} ?? 0);
            if ($val > 0) {
                $components[] = [
                    'description'  => $label . ' — ' . ($booking->event_name ?? 'Event'),
                    'item_type'    => 'banquet',
                    'quantity'     => 1,
                    'rate'         => $val,
                    'gross_amount' => $val,
                    'scope_hint'   => 'banquet',
                ];
            }
        }
        if (empty($components)) {
            $components[] = [
                'description' => 'Banquet — ' . ($booking->event_name ?? 'Event'),
                'item_type'   => 'banquet',
                'quantity'    => 1,
                'rate'        => (float) ($booking->subtotal ?? $booking->total_amount ?? 0),
                'gross_amount'=> (float) ($booking->subtotal ?? $booking->total_amount ?? 0),
                'scope_hint'  => 'banquet',
            ];
        }

        // Recipient from related guest or company
        $recipient = [
            'name'  => $booking->company?->name ?? trim(($booking->guest?->first_name ?? '') . ' ' . ($booking->guest?->last_name ?? '')) ?: 'Banquet client',
            'address' => $booking->company?->billing_address ?? null,
            'gstin' => $booking->company?->gstin ?? null,
            'state' => $booking->company?->billing_state ?? $property->state,
            'state_code' => $booking->company?->billing_state_code ?? $property->state_code,
        ];

        $supplierStateCode  = $this->resolveStateCode($property->state_code, $property->state);
        $recipientStateCode = $this->resolveStateCode($recipient['state_code'] ?? $supplierStateCode, $recipient['state'] ?? $property->state);

        return $this->build(
            tenantId: $booking->tenant_id,
            property: $property,
            source: $booking,
            rawLines: $components,
            recipient: array_merge($recipient, ['state_code' => $recipientStateCode]),
            placeOfSupply: $property->state ?: 'Unknown',
            placeOfSupplyCode: $supplierStateCode,
            isInterState: TaxEngine::isInterState($supplierStateCode, $recipientStateCode),
            amountPaid: (float) ($booking->advance_received ?? 0),
            issuedBy: $issuedBy,
        );
    }

    /**
     * Generic builder. All other source-specific helpers delegate here.
     */
    public function build(
        int $tenantId,
        Property $property,
        Model $source,
        array $rawLines,
        array $recipient,
        string $placeOfSupply,
        ?string $placeOfSupplyCode,
        bool $isInterState,
        float $amountPaid = 0,
        ?int $issuedBy = null,
    ): TaxInvoice {
        return app(TenantContext::class)->bypass(function () use (
            $tenantId, $property, $source, $rawLines, $recipient,
            $placeOfSupply, $placeOfSupplyCode, $isInterState, $amountPaid, $issuedBy
        ) {
            return DB::transaction(function () use (
                $tenantId, $property, $source, $rawLines, $recipient,
                $placeOfSupply, $placeOfSupplyCode, $isInterState, $amountPaid, $issuedBy
            ) {
                $invoiceDate = Carbon::today();
                $fy          = TaxEngine::financialYear($invoiceDate);
                $invoiceNo   = $this->nextInvoiceNumber($property, $fy);

                $invoice = new TaxInvoice([
                    'tenant_id'   => $tenantId,
                    'property_id' => $property->id,
                    'source_type' => $source::class,
                    'source_id'   => $source->getKey(),

                    'invoice_number' => $invoiceNo,
                    'financial_year' => $fy,
                    'document_type'  => TaxInvoice::TYPE_TAX_INVOICE,
                    'invoice_date'   => $invoiceDate,
                    'due_date'       => $invoiceDate,

                    // Supplier (snapshot)
                    'supplier_name'        => $property->name,
                    'supplier_legal_name'  => $property->legal_name,
                    'supplier_address'     => $property->address . ', ' . $property->city . ', ' . $property->state . ' - ' . $property->postal_code,
                    'supplier_state'       => $property->state,
                    'supplier_state_code'  => $this->resolveStateCode($property->state_code, $property->state),
                    'supplier_gstin'       => $property->gst_number,
                    'supplier_pan'         => $property->pan_number,
                    'supplier_email'       => $property->email,
                    'supplier_phone'       => $property->phone,

                    // Recipient
                    'recipient_name'        => $recipient['name'] ?? 'Guest',
                    'recipient_address'     => $recipient['address'] ?? null,
                    'recipient_state'       => $recipient['state'] ?? null,
                    'recipient_state_code'  => $recipient['state_code'] ?? null,
                    'recipient_gstin'       => $recipient['gstin'] ?? null,
                    'recipient_pan'         => $recipient['pan'] ?? null,
                    'recipient_email'       => $recipient['email'] ?? null,
                    'recipient_phone'       => $recipient['phone'] ?? null,

                    'place_of_supply'      => $placeOfSupply,
                    'place_of_supply_code' => $placeOfSupplyCode,
                    'is_inter_state'       => $isInterState,
                    'is_reverse_charge'    => false,

                    'gross_amount'    => 0,
                    'discount_amount' => 0,
                    'taxable_amount'  => 0,
                    'cgst_total'      => 0,
                    'sgst_total'      => 0,
                    'igst_total'      => 0,
                    'cess_total'      => 0,
                    'round_off'       => 0,
                    'grand_total'     => 0,
                    'amount_in_words' => '',
                    'amount_paid'     => $amountPaid,
                    'balance_due'     => 0,

                    'e_invoice_required' => (bool) $property->is_e_invoice_required,
                    'e_invoice_status'   => $property->is_e_invoice_required ? 'pending' : null,

                    'status'    => TaxInvoice::STATUS_ISSUED,
                    'issued_by' => $issuedBy ?? auth()->id(),
                    'issued_at' => now(),
                ]);
                $invoice->save();

                $totals = [
                    'gross' => 0, 'discount' => 0, 'taxable' => 0,
                    'cgst' => 0, 'sgst' => 0, 'igst' => 0, 'cess' => 0,
                ];
                $lineNo = 1;

                foreach ($rawLines as $raw) {
                    $qty   = (float) ($raw['quantity'] ?? 1);
                    $rate  = (float) ($raw['rate'] ?? 0);
                    $gross = (float) ($raw['gross_amount'] ?? ($qty * $rate));
                    $disc  = (float) ($raw['discount_amount'] ?? 0);
                    $taxable = max(0, $gross - $disc);

                    $tax = $this->engine->computeLine([
                        'scope'           => $this->mapHintToScope($raw['scope_hint'] ?? $raw['item_type'] ?? 'service'),
                        'property_id'     => $property->id,
                        'per_unit_rate'   => $rate,
                        'item_is_alcohol' => $raw['is_alcohol'] ?? false,
                        'item_is_tobacco' => $raw['is_tobacco'] ?? false,
                        'override_hsn'    => $raw['hsn_sac_code'] ?? null,
                    ], $taxable, $isInterState);

                    TaxInvoiceLine::create([
                        'tax_invoice_id'  => $invoice->id,
                        'line_no'         => $lineNo++,
                        'description'     => $raw['description'] ?? 'Item',
                        'hsn_sac_code'    => $tax['hsn_sac_code'],
                        'item_type'       => $raw['item_type'] ?? null,
                        'quantity'        => $qty,
                        'unit'            => $raw['unit'] ?? 'NOS',
                        'rate'            => $rate,
                        'gross_amount'    => $gross,
                        'discount_pct'    => $raw['discount_pct'] ?? 0,
                        'discount_amount' => $disc,
                        'taxable_amount'  => $taxable,
                        'cgst_rate'       => $tax['cgst_rate'],
                        'cgst_amount'     => $tax['cgst_amount'],
                        'sgst_rate'       => $tax['sgst_rate'],
                        'sgst_amount'     => $tax['sgst_amount'],
                        'igst_rate'       => $tax['igst_rate'],
                        'igst_amount'     => $tax['igst_amount'],
                        'cess_rate'       => $tax['cess_rate'],
                        'cess_amount'     => $tax['cess_amount'],
                        'total_amount'    => $tax['total_amount'],
                        'tax_rule_id'     => $tax['rule_id'],
                    ]);

                    $totals['gross']    += $gross;
                    $totals['discount'] += $disc;
                    $totals['taxable']  += $taxable;
                    $totals['cgst']     += $tax['cgst_amount'];
                    $totals['sgst']     += $tax['sgst_amount'];
                    $totals['igst']     += $tax['igst_amount'];
                    $totals['cess']     += $tax['cess_amount'];
                }

                $beforeRound = $totals['taxable']
                    + $totals['cgst'] + $totals['sgst']
                    + $totals['igst'] + $totals['cess'];
                $rounded = round($beforeRound);
                $roundOff = round($rounded - $beforeRound, 2);

                $invoice->fill([
                    'gross_amount'    => round($totals['gross'], 2),
                    'discount_amount' => round($totals['discount'], 2),
                    'taxable_amount'  => round($totals['taxable'], 2),
                    'cgst_total'      => round($totals['cgst'], 2),
                    'sgst_total'      => round($totals['sgst'], 2),
                    'igst_total'      => round($totals['igst'], 2),
                    'cess_total'      => round($totals['cess'], 2),
                    'round_off'       => $roundOff,
                    'grand_total'     => $rounded,
                    'balance_due'     => max(0, $rounded - $amountPaid),
                    'amount_in_words' => TaxInvoice::amountToWords($rounded),
                ])->save();

                return $invoice->load('lines');
            });
        });
    }

    /**
     * Atomic invoice number generation per property + financial year.
     * Format: {prefix}/{FY}/{seq:06}  e.g.  HTL/2026-27/000142
     */
    private function nextInvoiceNumber(Property $property, string $fy): string
    {
        return DB::transaction(function () use ($property, $fy) {
            $row = DB::table('invoice_sequences')
                ->where('property_id', $property->id)
                ->where('financial_year', $fy)
                ->where('document_type', 'tax_invoice')
                ->lockForUpdate()
                ->first();

            if (! $row) {
                DB::table('invoice_sequences')->insert([
                    'property_id'    => $property->id,
                    'financial_year' => $fy,
                    'document_type'  => 'tax_invoice',
                    'next_number'    => 2,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
                $seq = 1;
            } else {
                $seq = $row->next_number;
                DB::table('invoice_sequences')
                    ->where('id', $row->id)
                    ->update(['next_number' => $seq + 1, 'updated_at' => now()]);
            }

            $prefix = $property->invoice_prefix ?: 'INV';
            return sprintf('%s/%s/%06d', $prefix, $fy, $seq);
        });
    }

    /**
     * Resolve a 2-digit GST state code from either an existing code or the
     * state name. Falls back to '99' (Centre Jurisdiction) if nothing matches.
     */
    private function resolveStateCode(?string $code, ?string $stateName): ?string
    {
        if ($code) {
            $clean = str_pad(preg_replace('/\D/', '', $code), 2, '0', STR_PAD_LEFT);
            if ($clean !== '00') return $clean;
        }
        if (! $stateName) return null;

        $normalized = strtoupper(trim($stateName));
        foreach (TaxEngine::indianStateCodes() as $c => $name) {
            if (strtoupper(trim($name)) === $normalized) return $c;
        }
        // Try a softer "starts-with" match (e.g. "Andhra Pradesh (old)" vs "Andhra Pradesh")
        foreach (TaxEngine::indianStateCodes() as $c => $name) {
            if (str_starts_with(strtoupper($name), $normalized) || str_starts_with($normalized, strtoupper($name))) {
                return $c;
            }
        }
        return null;
    }

    private function mapHintToScope(string $hint): string
    {
        return match ($hint) {
            'room'       => TaxRule::SCOPE_ROOM,
            'fnb', 'pos','restaurant' => 'fnb', // engine resolves no-itc/with-itc
            'banquet'    => TaxRule::SCOPE_BANQUET,
            'liquor','alcohol','bar' => TaxRule::SCOPE_LIQUOR,
            'tobacco'    => TaxRule::SCOPE_TOBACCO,
            'service','spa','laundry','transport' => TaxRule::SCOPE_SERVICE,
            default => TaxRule::SCOPE_OTHER,
        };
    }

    /**
     * Best-effort folio → lines extraction. The Folio model in this codebase
     * doesn't expose charges directly here, so we emit ONE consolidated room
     * line for the folio total. If you have a folio_charges relation, this
     * is the place to enumerate them.
     */
    private function extractFolioLines(Folio $folio): array
    {
        // If the folio has a `charges` relation/method, prefer it.
        if (method_exists($folio, 'charges')) {
            try {
                $charges = $folio->charges()->get();
                if ($charges->count() > 0) {
                    return $charges->map(fn ($c) => [
                        'description'    => $c->description ?? 'Charge',
                        'item_type'      => $c->category ?? 'service',
                        'quantity'       => (float) ($c->quantity ?? 1),
                        'rate'           => (float) ($c->rate ?? $c->amount ?? 0),
                        'gross_amount'   => (float) ($c->amount ?? 0),
                        'discount_amount'=> (float) ($c->discount_amount ?? 0),
                        'hsn_sac_code'   => $c->hsn_sac_code ?? null,
                        'is_alcohol'     => (bool) ($c->is_alcohol ?? false),
                        'is_tobacco'     => (bool) ($c->is_tobacco ?? false),
                        'scope_hint'     => $c->scope_hint ?? ($c->category ?? 'service'),
                    ])->toArray();
                }
            } catch (\Throwable $e) {
                // table may not exist yet; fall through
            }
        }

        // Fallback — single consolidated line
        return [[
            'description'  => 'Accommodation — Folio ' . ($folio->folio_number ?: '#'.$folio->id),
            'item_type'    => 'room',
            'quantity'     => 1,
            'rate'         => (float) $folio->total_charges,
            'gross_amount' => (float) $folio->total_charges,
            'discount_amount' => (float) ($folio->total_discounts ?? 0),
            'scope_hint'   => 'room',
        ]];
    }
}
