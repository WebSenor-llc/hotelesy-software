<?php

namespace App\Mail;

use App\Models\Folio;
use App\Models\Property;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FolioInvoice extends Mailable
{
    use Queueable, SerializesModels;

    public Folio $folio;
    public ?Property $property;

    public function __construct(Folio $folio)
    {
        $this->folio = $folio->loadMissing(['guest', 'reservation.guest', 'charges', 'payments']);
        $this->property = Property::find($folio->property_id);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invoice {$this->folio->folio_number}" . ($this->property ? " — {$this->property->name}" : ''),
        );
    }

    public function content(): Content
    {
        $charges = $this->folio->charges->where('is_voided', false)->values();
        $payments = $this->folio->payments->whereIn('status', ['completed', 'pending'])->values();

        return new Content(
            view: 'emails.folio-invoice',
            with: [
                'folio' => $this->folio,
                'property' => $this->property,
                'charges' => $charges,
                'payments' => $payments,
            ],
        );
    }
}
