<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $folio->folio_number }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #1f2937; background:#f6f7f9; padding: 24px; }
        .wrap { max-width: 720px; margin: 0 auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
        .hdr { padding: 20px 24px; background: #0f172a; color: #fff; }
        .hdr h1 { margin: 0; font-size: 20px; }
        .hdr p { margin: 4px 0 0; font-size: 13px; opacity: .85; }
        .body { padding: 20px 24px; }
        .meta { display: flex; flex-wrap: wrap; gap: 24px; font-size: 13px; margin-bottom: 18px; }
        .meta div b { display:block; color:#6b7280; font-weight: 500; font-size: 11px; text-transform: uppercase; letter-spacing: .04em;}
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        th, td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { background: #f3f4f6; font-weight: 600; font-size: 12px; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        .totals { margin-top: 14px; font-size: 14px; }
        .totals .row { display: flex; justify-content: flex-end; gap: 24px; padding: 4px 0; }
        .totals .row .lbl { color: #6b7280; }
        .totals .row .val { min-width: 110px; text-align: right; font-variant-numeric: tabular-nums; }
        .totals .row.bal { font-weight: 700; border-top: 2px solid #0f172a; margin-top: 6px; padding-top: 8px; }
        .ftr { padding: 16px 24px; background: #f9fafb; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; }
        h2 { font-size: 14px; margin: 18px 0 6px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="hdr">
        <h1>{{ $property?->name ?? 'Invoice' }}</h1>
        <p>{{ $property?->legal_name ?? '' }}@if($property?->gst_number) · GSTIN: {{ $property->gst_number }}@endif</p>
    </div>
    <div class="body">
        <div class="meta">
            <div><b>Invoice / Folio</b>{{ $folio->folio_number }}</div>
            <div><b>Date</b>{{ now()->format('d M Y') }}</div>
            <div><b>Guest</b>{{ $folio->billing_name ?? ($folio->guest?->first_name . ' ' . $folio->guest?->last_name) }}</div>
            @if($folio->reservation)
                <div><b>Reservation</b>{{ $folio->reservation->reservation_number ?? ('#' . $folio->reservation->id) }}</div>
            @endif
            <div><b>Currency</b>{{ $folio->currency ?? 'INR' }}</div>
        </div>

        <h2>Charges</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Category</th>
                    <th class="num">Amount</th>
                    <th class="num">Tax</th>
                    <th class="num">Total</th>
                </tr>
            </thead>
            <tbody>
            @forelse($charges as $c)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($c->charge_date)->format('d M') }}</td>
                    <td>{{ $c->description }}</td>
                    <td>{{ $c->category }}</td>
                    <td class="num">{{ number_format((float) $c->amount, 2) }}</td>
                    <td class="num">{{ number_format((float) $c->tax_amount, 2) }}</td>
                    <td class="num">{{ number_format((float) $c->net_amount, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;color:#9ca3af;padding:14px 0;">No charges posted.</td></tr>
            @endforelse
            </tbody>
        </table>

        <h2>Payments</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Mode</th>
                    <th>Reference</th>
                    <th>Receipt</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
            @forelse($payments as $p)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($p->payment_date)->format('d M') }}</td>
                    <td>{{ strtoupper($p->mode) }}</td>
                    <td>{{ $p->transaction_reference ?? $p->upi_reference ?? '—' }}</td>
                    <td>{{ $p->receipt_number }}</td>
                    <td class="num">{{ number_format((float) $p->amount, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center;color:#9ca3af;padding:14px 0;">No payments yet.</td></tr>
            @endforelse
            </tbody>
        </table>

        <div class="totals">
            <div class="row"><div class="lbl">Sub-total charges</div><div class="val">{{ number_format((float) $folio->total_charges, 2) }}</div></div>
            <div class="row"><div class="lbl">Taxes</div><div class="val">{{ number_format((float) $folio->total_taxes, 2) }}</div></div>
            <div class="row"><div class="lbl">Discounts</div><div class="val">- {{ number_format((float) $folio->total_discounts, 2) }}</div></div>
            <div class="row"><div class="lbl">Payments received</div><div class="val">- {{ number_format((float) $folio->total_payments, 2) }}</div></div>
            <div class="row bal"><div class="lbl">Balance due</div><div class="val">{{ $folio->currency ?? 'INR' }} {{ number_format((float) $folio->balance, 2) }}</div></div>
        </div>
    </div>
    <div class="ftr">
        Thank you for staying with us. For queries on this invoice, reply to this email quoting folio {{ $folio->folio_number }}.
    </div>
</div>
</body>
</html>
