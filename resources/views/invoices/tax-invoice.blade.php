<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Tax Invoice {{ $invoice->invoice_number }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  * { box-sizing: border-box; }
  body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #111; margin: 0; padding: 24px; background: #fff; }
  .invoice { max-width: 820px; margin: 0 auto; border: 1.5px solid #111; }
  .row { display: flex; }
  .col { padding: 8px 12px; border-right: 1px solid #888; flex: 1; vertical-align: top; }
  .col:last-child { border-right: 0; }
  .border-top { border-top: 1px solid #111; }
  .border-bot { border-bottom: 1px solid #111; }
  .b { font-weight: 700; }
  .t-center { text-align: center; }
  .t-right  { text-align: right; }
  .t-up { text-transform: uppercase; letter-spacing: 0.04em; }
  .small { font-size: 10px; color: #444; }
  .mono { font-family: 'Courier New', monospace; }
  table { width: 100%; border-collapse: collapse; }
  th, td { padding: 5px 6px; border: 1px solid #555; vertical-align: top; }
  th { background: #f1f1f1; font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; text-align: left; }
  td.num { text-align: right; font-family: 'Courier New', monospace; }
  td.center { text-align: center; }
  .stamp { display: inline-block; padding: 4px 10px; border: 1px solid #111; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; }
  .header-bg { background: #111; color: #fff; }
  .qr { border: 1px solid #333; padding: 6px; display: inline-block; text-align: center; font-size: 9px; }
  .totals td { border: 0; padding: 3px 0; font-size: 11px; }
  .totals .lbl { color: #333; }
  .totals .grand { font-size: 14px; font-weight: 800; border-top: 2px solid #111; }
  .signbox { height: 70px; border-top: 1px solid #888; padding-top: 6px; text-align: center; font-size: 10px; color: #555; }
  .reverse { background: #fff5e6; }
  @media print { .no-print { display: none; } body { padding: 0; } .invoice { border: 1.5px solid #000; } }
</style>
</head>
<body>

<div class="invoice">

  {{-- Header strip --}}
  <div class="row header-bg">
    <div class="col" style="border-right: 1px solid #444; flex: 2;">
      <div class="t-up small" style="opacity: 0.7;">Tax Invoice</div>
      <div style="font-size: 18px; font-weight: 800;">{{ $invoice->supplier_name }}</div>
      <div class="small" style="opacity: 0.85; margin-top: 2px;">
        {{ $invoice->supplier_legal_name ?: $invoice->supplier_name }}<br>
        {{ $invoice->supplier_address }}
      </div>
    </div>
    <div class="col t-right" style="flex: 1;">
      <div class="small" style="opacity: 0.7;">{{ $invoice->document_type === 'tax_invoice' ? 'ORIGINAL FOR RECIPIENT' : strtoupper(str_replace('_',' ', $invoice->document_type)) }}</div>
      <div class="b" style="font-size: 13px; margin-top: 4px;">{{ $invoice->invoice_number }}</div>
      <div class="small">Date: {{ $invoice->invoice_date->format('d M Y') }}</div>
      <div class="small">FY: {{ $invoice->financial_year }}</div>
      @if($invoice->irn)
        <div class="small mono" style="margin-top: 3px; word-break: break-all;">IRN: {{ $invoice->irn }}</div>
      @endif
    </div>
  </div>

  {{-- Supplier identity --}}
  <div class="row border-top">
    <div class="col" style="flex:1;">
      <div class="small t-up b">Supplier · GSTIN</div>
      <div class="mono b">{{ $invoice->supplier_gstin ?? 'UNREGISTERED' }}</div>
      @if($invoice->supplier_pan)<div class="small">PAN: {{ $invoice->supplier_pan }}</div>@endif
      <div class="small">State: {{ $invoice->supplier_state }} ({{ $invoice->supplier_state_code }})</div>
      @if($invoice->supplier_email)<div class="small">{{ $invoice->supplier_email }} · {{ $invoice->supplier_phone }}</div>@endif
    </div>
    <div class="col" style="flex:1;">
      <div class="small t-up b">Bill to</div>
      <div class="b">{{ $invoice->recipient_name }}</div>
      @if($invoice->recipient_address)<div class="small">{{ $invoice->recipient_address }}</div>@endif
      @if($invoice->recipient_gstin)<div class="small mono">GSTIN: {{ $invoice->recipient_gstin }}</div>@endif
      @if($invoice->recipient_state)<div class="small">State: {{ $invoice->recipient_state }} ({{ $invoice->recipient_state_code }})</div>@endif
      @if($invoice->recipient_phone)<div class="small">{{ $invoice->recipient_phone }} · {{ $invoice->recipient_email }}</div>@endif
    </div>
    <div class="col" style="flex:1;">
      <div class="small t-up b">Place of supply</div>
      <div>{{ $invoice->place_of_supply }} ({{ $invoice->place_of_supply_code }})</div>
      <div class="small" style="margin-top:3px;">
        {{ $invoice->is_inter_state ? 'Inter-state · IGST' : 'Intra-state · CGST + SGST' }}
      </div>
      @if($invoice->is_reverse_charge)
        <div class="reverse small" style="padding: 2px 4px; margin-top: 3px;">⚠ Reverse charge applicable</div>
      @endif
      @if($invoice->is_export)
        <div class="small" style="margin-top: 3px;">Export {{ $invoice->is_sez ? '(SEZ)' : '' }}</div>
      @endif
    </div>
  </div>

  {{-- Line items table --}}
  <div class="border-top">
    <table>
      <thead>
        <tr>
          <th style="width: 28px;">#</th>
          <th>Description of goods / services</th>
          <th style="width: 70px;">HSN/SAC</th>
          <th style="width: 50px;" class="t-right">Qty</th>
          <th style="width: 38px;">Unit</th>
          <th style="width: 74px;" class="t-right">Rate</th>
          <th style="width: 78px;" class="t-right">Taxable</th>
          @if($invoice->is_inter_state)
            <th style="width: 80px;" class="t-right">IGST<br><span class="small">(rate / amt)</span></th>
          @else
            <th style="width: 80px;" class="t-right">CGST<br><span class="small">(rate / amt)</span></th>
            <th style="width: 80px;" class="t-right">SGST<br><span class="small">(rate / amt)</span></th>
          @endif
          @if($invoice->cess_total > 0)<th style="width: 60px;" class="t-right">Cess</th>@endif
          <th style="width: 88px;" class="t-right">Total</th>
        </tr>
      </thead>
      <tbody>
        @foreach($invoice->lines as $line)
          <tr>
            <td class="center">{{ $line->line_no }}</td>
            <td>
              {{ $line->description }}
              @if($line->discount_amount > 0)
                <div class="small" style="color:#9a3a3a;">Discount: ₹{{ number_format($line->discount_amount, 2) }}</div>
              @endif
            </td>
            <td class="center mono small">{{ $line->hsn_sac_code ?: '—' }}</td>
            <td class="num">{{ rtrim(rtrim(number_format($line->quantity, 3, '.', ''), '0'), '.') }}</td>
            <td class="center small">{{ $line->unit }}</td>
            <td class="num">₹{{ number_format($line->rate, 2) }}</td>
            <td class="num">₹{{ number_format($line->taxable_amount, 2) }}</td>
            @if($invoice->is_inter_state)
              <td class="num small">{{ rtrim(rtrim(number_format($line->igst_rate, 3, '.', ''), '0'), '.') }}%<br>₹{{ number_format($line->igst_amount, 2) }}</td>
            @else
              <td class="num small">{{ rtrim(rtrim(number_format($line->cgst_rate, 3, '.', ''), '0'), '.') }}%<br>₹{{ number_format($line->cgst_amount, 2) }}</td>
              <td class="num small">{{ rtrim(rtrim(number_format($line->sgst_rate, 3, '.', ''), '0'), '.') }}%<br>₹{{ number_format($line->sgst_amount, 2) }}</td>
            @endif
            @if($invoice->cess_total > 0)<td class="num small">₹{{ number_format($line->cess_amount, 2) }}</td>@endif
            <td class="num b">₹{{ number_format($line->total_amount, 2) }}</td>
          </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr style="background: #f7f7f7;">
          <td colspan="6" class="t-right b">Sub totals</td>
          <td class="num b">₹{{ number_format($invoice->taxable_amount, 2) }}</td>
          @if($invoice->is_inter_state)
            <td class="num b">₹{{ number_format($invoice->igst_total, 2) }}</td>
          @else
            <td class="num b">₹{{ number_format($invoice->cgst_total, 2) }}</td>
            <td class="num b">₹{{ number_format($invoice->sgst_total, 2) }}</td>
          @endif
          @if($invoice->cess_total > 0)<td class="num b">₹{{ number_format($invoice->cess_total, 2) }}</td>@endif
          <td class="num b">₹{{ number_format($invoice->taxable_amount + $invoice->cgst_total + $invoice->sgst_total + $invoice->igst_total + $invoice->cess_total, 2) }}</td>
        </tr>
      </tfoot>
    </table>
  </div>

  {{-- Totals + amount in words + QR --}}
  <div class="row border-top">
    <div class="col" style="flex: 1.4;">
      <div class="small t-up b" style="margin-bottom: 4px;">Amount in words</div>
      <div class="b" style="font-size: 12px;">{{ $invoice->amount_in_words }}</div>

      @if($invoice->is_reverse_charge)
        <div class="small" style="margin-top: 8px;">Tax payable on reverse charge basis: <span class="b">YES</span></div>
      @else
        <div class="small" style="margin-top: 8px;">Tax payable on reverse charge basis: NO</div>
      @endif

      @if($invoice->qr_code_payload)
        <div style="margin-top: 10px;">
          <div class="qr">
            {{-- QR placeholder. Populate via QrCode generator on signed_qr payload. --}}
            <div style="width: 110px; height: 110px; background: repeating-linear-gradient(45deg, #000 0 4px, #fff 4px 8px); border: 4px solid #000;"></div>
            <div style="margin-top: 4px;" class="b mono">e-Invoice QR</div>
          </div>
        </div>
      @endif
    </div>
    <div class="col" style="flex: 1;">
      <table class="totals">
        <tr><td class="lbl">Gross amount</td><td class="t-right mono">₹{{ number_format($invoice->gross_amount, 2) }}</td></tr>
        @if($invoice->discount_amount > 0)
          <tr><td class="lbl">Less: Discount</td><td class="t-right mono">−₹{{ number_format($invoice->discount_amount, 2) }}</td></tr>
        @endif
        <tr><td class="lbl">Taxable value</td><td class="t-right mono">₹{{ number_format($invoice->taxable_amount, 2) }}</td></tr>
        @if($invoice->is_inter_state)
          <tr><td class="lbl">IGST</td><td class="t-right mono">₹{{ number_format($invoice->igst_total, 2) }}</td></tr>
        @else
          <tr><td class="lbl">CGST</td><td class="t-right mono">₹{{ number_format($invoice->cgst_total, 2) }}</td></tr>
          <tr><td class="lbl">SGST</td><td class="t-right mono">₹{{ number_format($invoice->sgst_total, 2) }}</td></tr>
        @endif
        @if($invoice->cess_total > 0)<tr><td class="lbl">Cess</td><td class="t-right mono">₹{{ number_format($invoice->cess_total, 2) }}</td></tr>@endif
        @if(abs($invoice->round_off) > 0)
          <tr><td class="lbl">Round off</td><td class="t-right mono">{{ $invoice->round_off >= 0 ? '+' : '' }}₹{{ number_format($invoice->round_off, 2) }}</td></tr>
        @endif
        @if($invoice->tcs_amount > 0)
          <tr><td class="lbl">TCS u/s 206C</td><td class="t-right mono">₹{{ number_format($invoice->tcs_amount, 2) }}</td></tr>
        @endif
        <tr class="grand"><td>GRAND TOTAL</td><td class="t-right mono">₹{{ number_format($invoice->grand_total, 2) }}</td></tr>
        @if($invoice->amount_paid > 0)
          <tr><td class="lbl">Less: Paid</td><td class="t-right mono">−₹{{ number_format($invoice->amount_paid, 2) }}</td></tr>
          <tr class="b"><td>Balance due</td><td class="t-right mono">₹{{ number_format($invoice->balance_due, 2) }}</td></tr>
        @endif
      </table>
    </div>
  </div>

  {{-- Declaration & footer --}}
  <div class="row border-top">
    <div class="col" style="flex: 1.4;">
      <div class="small t-up b">Declaration</div>
      <div class="small" style="line-height: 1.5;">
        Certified that the particulars given above are true and correct, and the amount indicated represents the price actually charged.
        We hereby declare that GST has been computed in accordance with the provisions of the CGST/SGST/IGST Act, 2017.
        @if($invoice->irn)
          This is a digitally generated invoice. IRN registered with the Invoice Registration Portal (IRP).
        @endif
      </div>
      <div class="small" style="margin-top: 8px;">
        <div class="b">Bank details</div>
        Account name: {{ $invoice->supplier_legal_name ?: $invoice->supplier_name }}<br>
        For payment terms & remittance details, please refer to your contract or contact our accounts team.
      </div>
    </div>
    <div class="col" style="flex: 1;">
      <div class="small t-up b">For {{ $invoice->supplier_name }}</div>
      <div class="signbox">Authorised signatory</div>
    </div>
  </div>

  <div class="row border-top header-bg">
    <div class="col" style="flex: 1; padding: 6px 12px;">
      <span class="small" style="opacity: 0.7;">This is a computer-generated invoice issued under Rule 46 of CGST Rules, 2017. Subject to {{ $invoice->place_of_supply }} jurisdiction.</span>
    </div>
  </div>

</div>

<div class="no-print" style="max-width: 820px; margin: 16px auto; text-align: right;">
  <button onclick="window.print()" style="background:#111;color:#fff;padding:10px 18px;border:0;border-radius:6px;cursor:pointer;">Print invoice</button>
</div>

</body>
</html>
