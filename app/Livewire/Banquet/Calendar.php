<?php

namespace App\Livewire\Banquet;

use App\Models\Banquet\BanquetBooking;
use App\Models\Banquet\BanquetHall;
use App\Models\Banquet\BanquetPackage;
use App\Models\Company;
use App\Models\Guest;
use App\Models\Payment;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class Calendar extends Component
{
    public string $month;

    // Form state
    public bool $showForm = false;
    public ?int $editingId = null;
    public ?int $detailId = null;

    public ?int $hallId = null;
    public ?int $packageId = null;
    public ?int $guestId = null;
    public ?int $companyId = null;
    public string $eventName = '';
    public string $eventType = 'wedding';
    public string $eventDate = '';
    public string $eventStart = '18:00';
    public string $eventEnd = '23:00';
    public int $expectedPax = 100;
    public ?int $actualPax = null;
    public float $hallRent = 0;
    public float $foodAmount = 0;
    public float $beverageAmount = 0;
    public float $decorAmount = 0;
    public float $avAmount = 0;
    public float $otherAmount = 0;
    public float $advanceReceived = 0;
    public string $status = 'tentative';
    public string $menuDetails = '';
    public string $setupNotes = '';
    public string $specialRequests = '';

    // Advance payment form
    public float $newAdvanceAmount = 0;
    public string $newAdvanceMode = 'bank_transfer';
    public string $newAdvanceRef = '';

    public function mount(): void
    {
        $this->month = today()->format('Y-m');
        $this->eventDate = today()->format('Y-m-d');
    }

    public function shift(int $offset): void
    {
        $this->month = Carbon::parse($this->month . '-01')->addMonths($offset)->format('Y-m');
    }

    public function jumpToToday(): void
    {
        $this->month = today()->format('Y-m');
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showForm = true;
    }

    public function startEdit(int $id): void
    {
        $b = BanquetBooking::findOrFail($id);
        $this->editingId = $b->id;
        $this->hallId = $b->hall_id;
        $this->packageId = $b->package_id;
        $this->guestId = $b->guest_id;
        $this->companyId = $b->company_id;
        $this->eventName = $b->event_name;
        $this->eventType = $b->event_type;
        $this->eventDate = $b->event_date->format('Y-m-d');
        $this->eventStart = substr((string) $b->event_start_time, 0, 5);
        $this->eventEnd = substr((string) $b->event_end_time, 0, 5);
        $this->expectedPax = (int) $b->expected_pax;
        $this->actualPax = $b->actual_pax;
        $this->hallRent = (float) $b->hall_rent;
        $this->foodAmount = (float) $b->food_amount;
        $this->beverageAmount = (float) $b->beverage_amount;
        $this->decorAmount = (float) $b->decor_amount;
        $this->avAmount = (float) $b->av_amount;
        $this->otherAmount = (float) $b->other_amount;
        $this->advanceReceived = (float) $b->advance_received;
        $this->status = $b->status;
        $this->menuDetails = (string) $b->menu_details;
        $this->setupNotes = (string) $b->setup_notes;
        $this->specialRequests = (string) $b->special_requests;
        $this->showForm = true;
        $this->detailId = null;
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->hallId = null;
        $this->packageId = null;
        $this->guestId = null;
        $this->companyId = null;
        $this->eventName = '';
        $this->eventType = 'wedding';
        $this->eventDate = today()->format('Y-m-d');
        $this->eventStart = '18:00';
        $this->eventEnd = '23:00';
        $this->expectedPax = 100;
        $this->actualPax = null;
        $this->hallRent = 0;
        $this->foodAmount = 0;
        $this->beverageAmount = 0;
        $this->decorAmount = 0;
        $this->avAmount = 0;
        $this->otherAmount = 0;
        $this->advanceReceived = 0;
        $this->status = 'tentative';
        $this->menuDetails = '';
        $this->setupNotes = '';
        $this->specialRequests = '';
    }

    public function save(): void
    {
        $this->validate([
            'hallId' => 'required|exists:banquet_halls,id',
            'eventName' => 'required|min:3',
            'eventDate' => 'required|date',
            'expectedPax' => 'required|integer|min:1',
        ]);

        $ctx = app(TenantContext::class);
        $data = [
            'tenant_id' => $ctx->tenantId(),
            'property_id' => $ctx->propertyId(),
            'hall_id' => $this->hallId,
            'package_id' => $this->packageId,
            'guest_id' => $this->guestId,
            'company_id' => $this->companyId,
            'event_name' => $this->eventName,
            'event_type' => $this->eventType,
            'event_date' => $this->eventDate,
            'event_start_time' => $this->eventStart . ':00',
            'event_end_time' => $this->eventEnd . ':00',
            'expected_pax' => $this->expectedPax,
            'actual_pax' => $this->actualPax,
            'hall_rent' => $this->hallRent,
            'food_amount' => $this->foodAmount,
            'beverage_amount' => $this->beverageAmount,
            'decor_amount' => $this->decorAmount,
            'av_amount' => $this->avAmount,
            'other_amount' => $this->otherAmount,
            'advance_received' => $this->advanceReceived,
            'status' => $this->status,
            'menu_details' => $this->menuDetails,
            'setup_notes' => $this->setupNotes,
            'special_requests' => $this->specialRequests,
            'created_by' => auth()->id(),
        ];

        if ($this->editingId) {
            $b = BanquetBooking::findOrFail($this->editingId);
            $b->update($data);
        } else {
            $data['booking_number'] = 'BQT-' . now()->format('ymd') . '-' . str_pad((string) (BanquetBooking::count() + 1), 4, '0', STR_PAD_LEFT);
            $b = BanquetBooking::create($data);
        }
        $b->recomputeTotals();

        session()->flash('success', $this->editingId ? "Event {$b->booking_number} updated." : "Event {$b->booking_number} created.");
        $this->showForm = false;
        $this->resetForm();
    }

    public function openDetail(int $id): void
    {
        $this->detailId = $id;
        $this->showForm = false;
        $this->newAdvanceAmount = 0;
        $this->newAdvanceMode = 'bank_transfer';
        $this->newAdvanceRef = '';
    }
    public function closeDetail(): void { $this->detailId = null; }

    public function changeStatus(int $id, string $newStatus): void
    {
        $allowed = ['enquiry', 'tentative', 'confirmed', 'completed', 'cancelled'];
        if (!in_array($newStatus, $allowed)) return;
        $b = BanquetBooking::findOrFail($id);
        $b->update(['status' => $newStatus]);
        session()->flash('success', "Status updated to {$newStatus}.");
    }

    public function takeAdvance(): void
    {
        if (!$this->detailId || $this->newAdvanceAmount <= 0) {
            session()->flash('error', 'Enter an amount.');
            return;
        }
        $b = BanquetBooking::findOrFail($this->detailId);
        $ctx = app(TenantContext::class);

        DB::transaction(function () use ($b, $ctx) {
            Payment::create([
                'tenant_id' => $ctx->tenantId(),
                'property_id' => $ctx->propertyId(),
                'reservation_id' => null,
                'folio_id' => null,
                'company_id' => $b->company_id,
                'receipt_number' => 'BQT-ADV-' . now()->format('ymd') . '-' . str_pad((string) (Payment::count() + 1), 4, '0', STR_PAD_LEFT),
                'payment_date' => today()->toDateString(),
                'business_date' => today()->toDateString(),
                'mode' => $this->newAdvanceMode,
                'amount' => $this->newAdvanceAmount,
                'currency' => 'INR',
                'transaction_reference' => $this->newAdvanceRef ?: null,
                'status' => 'completed',
                'received_by' => auth()->id(),
                'notes' => "Banquet advance · {$b->booking_number} · {$b->event_name}",
                'payable_type' => BanquetBooking::class,
                'payable_id' => $b->id,
            ]);

            $b->update(['advance_received' => (float) $b->advance_received + $this->newAdvanceAmount]);
        });

        session()->flash('success', '₹' . number_format($this->newAdvanceAmount, 2) . ' advance recorded.');
        $this->newAdvanceAmount = 0;
        $this->newAdvanceRef = '';
    }

    public function deleteBooking(int $id): void
    {
        $b = BanquetBooking::findOrFail($id);
        $b->delete();
        $this->detailId = null;
        session()->flash('success', "Event {$b->booking_number} cancelled.");
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();
        $monthStart = Carbon::parse($this->month . '-01');
        $monthEnd = $monthStart->copy()->endOfMonth();

        $halls = BanquetHall::where('property_id', $propertyId)->where('is_active', true)->get();
        $packages = BanquetPackage::where('property_id', $propertyId)->where('is_active', true)->get();
        $bookings = BanquetBooking::where('property_id', $propertyId)
            ->whereBetween('event_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->with(['hall', 'guest', 'company', 'package'])
            ->orderBy('event_date')->orderBy('event_start_time')
            ->get();

        $byDate = $bookings->groupBy(fn ($b) => $b->event_date->toDateString());
        $stats = [
            'count' => $bookings->count(),
            'total_revenue' => $bookings->sum('total_amount'),
            'pax' => $bookings->sum('expected_pax'),
            'confirmed' => $bookings->where('status', 'confirmed')->count(),
        ];

        $guests = Guest::orderBy('first_name')->limit(500)->get();
        $companies = Company::orderBy('name')->limit(500)->get();

        $detail = $this->detailId ? BanquetBooking::with(['hall', 'guest', 'company', 'package', 'salesOwner'])->find($this->detailId) : null;
        $detailPayments = $detail
            ? Payment::where('payable_type', BanquetBooking::class)
                ->where('payable_id', $detail->id)
                ->orderBy('payment_date')->get()
            : collect();

        return view('livewire.banquet.calendar', compact(
            'halls', 'packages', 'bookings', 'byDate', 'monthStart', 'monthEnd', 'stats',
            'guests', 'companies', 'detail', 'detailPayments'
        ));
    }
}
