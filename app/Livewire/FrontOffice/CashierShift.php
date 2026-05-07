<?php

namespace App\Livewire\FrontOffice;

use App\Models\Payment;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class CashierShift extends Component
{
    public string $shiftDate;
    public ?int $cashierId = null;

    // Handover sheet state
    public bool $printMode = false;
    public float $openingCash = 0;
    public float $currentCash = 0;
    public string $shiftOpenedAt = '';
    public string $attendantName = '';

    public function mount(): void
    {
        $this->shiftDate = today()->toDateString();
        $this->cashierId = auth()->id();
        $this->shiftOpenedAt = today()->setTime(8, 0)->toDateTimeString();
        $this->attendantName = auth()->user()?->name ?? '';
        // Restore counted cash from session so it survives reload
        $this->currentCash = (float) session()->get($this->cashSessionKey(), 0);
        $this->openingCash = (float) session()->get($this->openingCashSessionKey(), $this->openingCash);
    }

    public function updatedCurrentCash(): void
    {
        session()->put($this->cashSessionKey(), (float) $this->currentCash);
    }

    public function updatedOpeningCash(): void
    {
        session()->put($this->openingCashSessionKey(), (float) $this->openingCash);
    }

    private function cashSessionKey(): string
    {
        return 'cashier_shift.current_cash.' . (auth()->id() ?? 'guest') . '.' . $this->shiftDate;
    }

    private function openingCashSessionKey(): string
    {
        return 'cashier_shift.opening_cash.' . (auth()->id() ?? 'guest') . '.' . $this->shiftDate;
    }

    public function printHandover(): void
    {
        // Persist current cash so it survives a reload after printing
        session()->put($this->cashSessionKey(), (float) $this->currentCash);
        session()->put($this->openingCashSessionKey(), (float) $this->openingCash);
        $this->printMode = true;
        $this->dispatch('print-handover');
    }

    public function exitPrintMode(): void
    {
        $this->printMode = false;
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();
        $d = Carbon::parse($this->shiftDate);

        $q = Payment::where('property_id', $propertyId)->whereDate('payment_date', $d);
        if ($this->cashierId) $q->where('received_by', $this->cashierId);
        $payments = $q->where('status','completed')->orderByDesc('created_at')->get();

        $byMode = $payments->groupBy('mode')->map(fn ($g)=>['mode'=>$g->first()->mode,'count'=>$g->count(),'amount'=>$g->sum('amount')])->values();
        $totals = [
            'count' => $payments->count(),
            'total' => (float) $payments->sum('amount'),
            'cash'  => (float) $payments->where('mode','cash')->sum('amount'),
            'card'  => (float) $payments->where('mode','card')->sum('amount'),
            'upi'   => (float) $payments->where('mode','upi')->sum('amount'),
            'other' => (float) $payments->whereNotIn('mode',['cash','card','upi'])->sum('amount'),
        ];

        $expectedClose = $this->openingCash + $totals['cash'];

        return view('livewire.front-office.cashier-shift', compact('payments','byMode','totals','d','expectedClose'));
    }
}
