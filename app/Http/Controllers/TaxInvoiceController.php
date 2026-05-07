<?php

namespace App\Http\Controllers;

use App\Models\Folio;
use App\Models\TaxInvoice;
use App\Services\Tax\InvoiceBuilder;
use App\Services\TenantContext;
use Illuminate\Http\Request;

class TaxInvoiceController extends Controller
{
    /**
     * View a tax invoice on screen (HTML, government-compliant layout).
     * Same template is used for the PDF export.
     *
     * Note: route param is `{id}` (not `{invoice}`) so we explicitly fetch
     * with TenantContext::bypass and surface a friendly error instead of a
     * silent 404 from the BelongsToTenant global scope.
     */
    public function show(Request $request, int $id)
    {
        $invoice = $this->loadInvoice($id);
        return view('invoices.tax-invoice', ['invoice' => $invoice]);
    }

    /**
     * Stream the invoice as a PDF if dompdf is available; otherwise serve HTML.
     */
    public function pdf(Request $request, int $id)
    {
        $invoice = $this->loadInvoice($id);

        $html = view('invoices.tax-invoice', ['invoice' => $invoice])->render();

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4');
            return $pdf->stream($invoice->invoice_number . '.pdf');
        }
        return response($html)->header('Content-Type', 'text/html');
    }

    /**
     * Build a TaxInvoice on demand from a Folio (called from the FolioView page).
     */
    public function buildFromFolio(Request $request, Folio $folio, InvoiceBuilder $builder)
    {
        $ctx = app(TenantContext::class);
        if ($folio->tenant_id !== $ctx->tenantId() && ! auth()->user()?->is_super_admin) {
            abort(403, 'Folio not in current tenant.');
        }

        // Reuse if already built
        $existing = $ctx->bypass(fn () => TaxInvoice::where('source_type', Folio::class)
            ->where('source_id', $folio->id)
            ->orderByDesc('id')
            ->first());
        if ($existing) {
            return redirect()->route('invoice.show', $existing->id);
        }

        try {
            $invoice = $builder->buildFromFolio($folio, auth()->id());
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to build invoice: ' . $e->getMessage());
        }
        return redirect()->route('invoice.show', $invoice->id);
    }

    /**
     * Always-fetch with tenant bypass + friendly 404 message so we never
     * silently fail when the BelongsToTenant scope doesn't match.
     */
    private function loadInvoice(int $id): TaxInvoice
    {
        $user = auth()->user();
        if (! $user) abort(401);

        $ctx = app(TenantContext::class);
        $invoice = $ctx->bypass(function () use ($id) {
            return TaxInvoice::with('lines')->find($id);
        });

        if (! $invoice) {
            abort(404, "Tax invoice #{$id} not found. "
                . "Check /invoices to see what's been issued.");
        }

        if (! $user->is_super_admin && $invoice->tenant_id !== $user->tenant_id) {
            abort(403, 'You don\'t have access to this invoice.');
        }

        return $invoice;
    }
}
