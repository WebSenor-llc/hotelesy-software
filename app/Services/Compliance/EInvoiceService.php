<?php

namespace App\Services\Compliance;

use App\Models\Company;
use App\Models\Compliance\EInvoice;
use App\Models\Folio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Mock IRN/QR generator. In production this would call NIC IRP API.
 *
 * Generates a SHA-256 hash IRN and a JSON payload conforming to the
 * GST IRP schema (subset: Document, Seller, Buyer, ItemList, ValueDetails).
 */
class EInvoiceService
{
    public function shouldGenerate(Folio $folio): bool
    {
        $folio->loadMissing(['company', 'reservation.property']);

        $property = $folio->reservation?->property
            ?? \App\Models\Property::find($folio->property_id);
        if (!$property || !$property->einvoice_required) {
            return false;
        }

        $company = $folio->company;
        if (!$company || !$company->gst_number) {
            return false;
        }

        return true;
    }

    public function generate(Folio $folio): ?EInvoice
    {
        if (!$this->shouldGenerate($folio)) {
            return null;
        }

        return DB::transaction(function () use ($folio) {
            $folio->loadMissing(['company', 'reservation.property', 'charges']);
            $property = $folio->reservation?->property
                ?? \App\Models\Property::find($folio->property_id);
            $company = $folio->company;

            $invoiceNumber = $folio->invoice_number ?: ('INV-' . $folio->folio_number);
            $existing = EInvoice::where('property_id', $property->id)
                ->where('invoice_number', $invoiceNumber)
                ->first();

            if ($existing && $existing->status === EInvoice::STATUS_GENERATED) {
                return $existing;
            }

            $sellerGstin = $property->gst_number ?? '';
            $buyerGstin = $company->gst_number ?? '';
            $today = now()->toDateString();

            $irn = hash('sha256', "{$invoiceNumber}-{$sellerGstin}-{$buyerGstin}-{$today}");
            $ackNumber = strtoupper(substr(md5($irn), 0, 16));

            $payload = $this->buildPayload($folio, $property, $company, $invoiceNumber);

            $row = $existing ?? new EInvoice([
                'tenant_id' => $folio->tenant_id,
                'property_id' => $property->id,
                'folio_id' => $folio->id,
                'invoice_number' => $invoiceNumber,
            ]);

            $row->fill([
                'irn' => $irn,
                'ack_number' => $ackNumber,
                'ack_date' => now(),
                'qr_code_data' => $irn,
                'json_payload' => $payload,
                'status' => EInvoice::STATUS_GENERATED,
                'error_message' => null,
                'generated_at' => now(),
            ])->save();

            return $row;
        });
    }

    public function cancel(EInvoice $eInvoice, string $reason = ''): bool
    {
        // 24-hour cancellation window per IRP rules
        if ($eInvoice->status !== EInvoice::STATUS_GENERATED) {
            return false;
        }
        if ($eInvoice->generated_at && $eInvoice->generated_at->diffInHours(now()) > 24) {
            return false;
        }

        $eInvoice->update([
            'status' => EInvoice::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason ?: 'Cancelled by user',
        ]);
        return true;
    }

    private function buildPayload(Folio $folio, $property, Company $company, string $invoiceNumber): array
    {
        $charges = $folio->charges()->where('is_voided', false)->get();
        $items = $charges->map(function ($c, $i) {
            $assessable = (float) ($c->amount ?? 0);
            $taxAmount = (float) ($c->tax_amount ?? 0);
            $net = (float) ($c->net_amount ?? ($assessable + $taxAmount));
            $rate = $assessable > 0 ? round($taxAmount / $assessable * 100, 2) : 0.0;
            return [
                'SlNo' => (string) ($i + 1),
                'PrdDesc' => $c->description ?? 'Hotel charge',
                'IsServc' => 'Y',
                'HsnCd' => '996311', // hotel accommodation default
                'Qty' => (float) ($c->quantity ?? 1),
                'UnitPrice' => round((float) ($c->rate ?? $assessable), 2),
                'TotAmt' => round($assessable, 2),
                'AssAmt' => round($assessable, 2),
                'GstRt' => $rate,
                'IgstAmt' => round($taxAmount, 2),
                'CgstAmt' => 0,
                'SgstAmt' => 0,
                'TotItemVal' => round($net, 2),
            ];
        })->all();

        return [
            'Version' => '1.1',
            'TranDtls' => [
                'TaxSch' => 'GST',
                'SupTyp' => 'B2B',
                'IgstOnIntra' => 'N',
            ],
            'DocDtls' => [
                'Typ' => 'INV',
                'No' => $invoiceNumber,
                'Dt' => now()->format('d/m/Y'),
            ],
            'SellerDtls' => [
                'Gstin' => $property->gst_number,
                'LglNm' => $property->legal_name ?? $property->name,
                'TrdNm' => $property->name,
                'Addr1' => substr((string) $property->address, 0, 100),
                'Loc' => $property->city ?? '',
                'Pin' => (int) preg_replace('/\D/', '', (string) $property->postal_code) ?: 0,
                'Stcd' => $property->state ?? '',
            ],
            'BuyerDtls' => [
                'Gstin' => $company->gst_number,
                'LglNm' => $company->legal_name ?? $company->name,
                'Pos' => $company->state ?? '',
                'Addr1' => substr((string) $company->billing_address, 0, 100),
                'Loc' => $company->city ?? '',
                'Pin' => (int) preg_replace('/\D/', '', (string) $company->postal_code) ?: 0,
                'Stcd' => $company->state ?? '',
            ],
            'ItemList' => $items,
            'ValDtls' => [
                'AssVal' => round((float) $folio->total_charges, 2),
                'IgstVal' => round((float) $folio->total_taxes, 2),
                'CgstVal' => 0,
                'SgstVal' => 0,
                'Discount' => round((float) $folio->total_discounts, 2),
                'TotInvVal' => round(((float) $folio->total_charges) + ((float) $folio->total_taxes) - ((float) $folio->total_discounts), 2),
            ],
        ];
    }
}
