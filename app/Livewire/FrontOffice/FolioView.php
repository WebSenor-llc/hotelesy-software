<?php

namespace App\Livewire\FrontOffice;

use App\Mail\FolioInvoice;
use App\Models\Compliance\EInvoice;
use App\Models\Folio;
use App\Models\FolioCharge;
use App\Services\Billing\FolioService;
use App\Services\Compliance\EInvoiceService;
use App\Services\Compliance\TdsCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class FolioView extends Component
{
    public $folio;

    // Void charge UI
    public ?int $voidingChargeId = null;
    public string $voidReason = '';

    public function mount(int $folio): void
    {
        $ctx = app(\App\Services\TenantContext::class);
        $this->folio = Folio::where('property_id', $ctx->propertyId())
            ->where('id', $folio)
            ->firstOrFail()
            ->load(['reservation', 'reservationRoom']);
    }

    public function startVoid(int $chargeId): void
    {
        $this->voidingChargeId = $chargeId;
        $this->voidReason = '';
    }

    public function cancelVoid(): void
    {
        $this->voidingChargeId = null;
        $this->voidReason = '';
    }

    public function voidCharge(int $chargeId, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            session()->flash('error', 'Void reason is required.');
            return;
        }

        $ctx = app(\App\Services\TenantContext::class);
        $charge = FolioCharge::where('property_id', $ctx->propertyId())
            ->where('folio_id', $this->folio->id)
            ->findOrFail($chargeId);

        try {
            app(FolioService::class)->voidCharge($charge, $reason, auth()->id());
            session()->flash('success', 'Charge voided.');
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->voidingChargeId = null;
        $this->voidReason = '';
        $this->folio->refresh();
    }

    public function printBill(): void
    {
        $this->dispatch('print-folio');
    }

    public function generateEInvoice(): void
    {
        $svc = app(EInvoiceService::class);
        if (!$svc->shouldGenerate($this->folio)) {
            session()->flash('error', 'E-invoice not required (property turnover < ₹5cr or no buyer GSTIN).');
            return;
        }
        $row = $svc->generate($this->folio);
        if ($row) {
            session()->flash('success', 'E-invoice IRN generated: ' . substr($row->irn, 0, 16) . '…');
        } else {
            session()->flash('error', 'Failed to generate e-invoice.');
        }
        $this->folio->refresh();
    }

    /**
     * Issue a government-compliant tax invoice for this folio. Reuses if
     * one already exists; otherwise builds a new one through InvoiceBuilder.
     */
    public function issueGstInvoice(): void
    {
        $ctx = app(\App\Services\TenantContext::class);
        $existing = \App\Models\TaxInvoice::where('source_type', \App\Models\Folio::class)
            ->where('source_id', $this->folio->id)
            ->orderByDesc('id')
            ->first();
        if ($existing) {
            $this->redirect(route('invoice.show', $existing));
            return;
        }
        try {
            $invoice = app(\App\Services\Tax\InvoiceBuilder::class)
                ->buildFromFolio($this->folio, auth()->id());
            session()->flash('success', 'Tax invoice ' . $invoice->invoice_number . ' issued.');
            $this->redirect(route('invoice.show', $invoice));
        } catch (\Throwable $e) {
            session()->flash('error', 'Failed to issue invoice: ' . $e->getMessage());
        }
    }

    public function emailInvoice(): void
    {
        $ctx = app(\App\Services\TenantContext::class);

        $this->folio->loadMissing(['guest', 'reservation.guest']);
        $email = $this->folio->guest?->email ?? $this->folio->reservation?->guest?->email;

        if (!$email) {
            session()->flash('error', 'No email on file for this guest.');
            return;
        }

        try {
            Mail::to($email)->queue(new FolioInvoice($this->folio));
        } catch (\Throwable $e) {
            session()->flash('error', 'Failed to queue invoice email: ' . $e->getMessage());
            return;
        }

        $this->folio->update(['invoice_generated_at' => now()]);

        // Audit log: keep the lightweight row in whichever notification/outbox/integration table exists.
        foreach (['notification_log', 'outbox', 'communications_log', 'integration_logs'] as $table) {
            if (Schema::hasTable($table)) {
                try {
                    if ($table === 'integration_logs') {
                        DB::table('integration_logs')->insert([
                            'tenant_id' => $ctx->tenantId(),
                            'property_id' => $ctx->propertyId(),
                            'integration' => 'email',
                            'operation' => 'send_invoice',
                            'reference_type' => 'folio',
                            'reference_id' => $this->folio->id,
                            'success' => true,
                            'duration_ms' => 0,
                            'called_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        DB::table($table)->insert([
                            'tenant_id' => $ctx->tenantId(),
                            'property_id' => $ctx->propertyId(),
                            'channel' => 'email',
                            'recipient' => $email,
                            'subject' => "Invoice for folio {$this->folio->folio_number}",
                            'status' => 'queued',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    break;
                } catch (\Throwable $e) {
                    // try next table
                }
            }
        }

        session()->flash('success', "Invoice emailed to {$email}.");
    }

    public function render()
    {
        $this->folio->loadMissing(['company', 'reservation.property']);
        $property = $this->folio->reservation?->property
            ?? \App\Models\Property::find($this->folio->property_id);

        $tds = app(TdsCalculator::class)->calculateForFolio($this->folio);

        $eInvoice = EInvoice::where('folio_id', $this->folio->id)
            ->whereIn('status', [EInvoice::STATUS_GENERATED, EInvoice::STATUS_DRAFT])
            ->latest('generated_at')
            ->first();

        $showEInvoiceButton = $property
            && $property->einvoice_required
            && $this->folio->company
            && $this->folio->company->gst_number
            && !$eInvoice;

        return view('livewire.front-office.folio-view', [
            'charges'   => $this->folio->charges()->orderBy('created_at')->get(),
            'payments'  => $this->folio->payments()->orderBy('created_at')->get(),
            'tds'       => $tds,
            'eInvoice'  => $eInvoice,
            'showEInvoiceButton' => $showEInvoiceButton,
            'property'  => $property,
        ]);
    }
}
