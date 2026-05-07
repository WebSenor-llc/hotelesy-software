<?php

namespace App\Livewire\Amenities;

use App\Models\Amenities\Amenity;
use App\Models\Amenities\AmenityOrder;
use App\Models\Folio;
use App\Models\FolioCharge;
use App\Models\Reservation;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class AmenitiesHub extends Component
{
    public string $typeFilter = '';
    public string $tab = 'services';

    // Amenity create/edit
    public bool $showAmenityForm = false;
    public ?int $editingAmenityId = null;
    public string $code = '';
    public string $name = '';
    public string $description = '';
    public string $category = 'spa';
    public string $pricingType = 'flat';
    public float $price = 0;
    public float $taxPercent = 18;
    public bool $isActive = true;

    // Order form
    public bool $showOrderForm = false;
    public ?int $orderAmenityId = null;
    public ?int $orderReservationId = null;
    public string $serviceDate = '';
    public string $serviceTime = '';
    public int $orderQty = 1;
    public string $orderNotes = '';
    public bool $chargeToFolio = true;

    public function mount(): void
    {
        $this->serviceDate = today()->toDateString();
        $this->serviceTime = '10:00';
    }

    public function setTab(string $t): void { $this->tab = $t; }

    public function startCreateAmenity(): void
    {
        $this->resetAmenityForm();
        $this->editingAmenityId = null;
        $this->showAmenityForm = true;
    }

    public function startEditAmenity(int $id): void
    {
        $a = Amenity::findOrFail($id);
        $this->editingAmenityId = $a->id;
        $this->code = $a->code;
        $this->name = $a->name;
        $this->description = (string) $a->description;
        $this->category = $a->category;
        $this->pricingType = $a->pricing_type;
        $this->price = (float) $a->price;
        $this->taxPercent = (float) $a->tax_percent;
        $this->isActive = (bool) $a->is_active;
        $this->showAmenityForm = true;
    }

    private function resetAmenityForm(): void
    {
        $this->editingAmenityId = null;
        $this->code = '';
        $this->name = '';
        $this->description = '';
        $this->category = 'spa';
        $this->pricingType = 'flat';
        $this->price = 0;
        $this->taxPercent = 18;
        $this->isActive = true;
    }

    public function cancelAmenityForm(): void { $this->showAmenityForm = false; }

    public function saveAmenity(): void
    {
        $this->validate([
            'code' => 'required|min:2|max:30',
            'name' => 'required|min:2',
            'price' => 'required|numeric|min:0',
        ]);
        $ctx = app(TenantContext::class);
        $data = [
            'tenant_id' => $ctx->tenantId(),
            'property_id' => $ctx->propertyId(),
            'code' => strtoupper($this->code),
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'pricing_type' => $this->pricingType,
            'price' => $this->price,
            'tax_percent' => $this->taxPercent,
            'is_active' => $this->isActive,
            'available_at_booking' => true,
            'available_at_checkin' => true,
            'available_in_stay' => true,
        ];
        if ($this->editingAmenityId) {
            Amenity::findOrFail($this->editingAmenityId)->update($data);
            session()->flash('success', "Amenity {$this->name} updated.");
        } else {
            Amenity::create($data);
            session()->flash('success', "Amenity {$this->name} created.");
        }
        $this->showAmenityForm = false;
        $this->resetAmenityForm();
    }

    public function deleteAmenity(int $id): void
    {
        $a = Amenity::findOrFail($id);
        $a->update(['is_active' => false]);
        session()->flash('success', "{$a->name} deactivated.");
    }

    // Order flow
    public function startOrder(?int $amenityId = null): void
    {
        $this->orderAmenityId = $amenityId;
        $this->orderReservationId = null;
        $this->orderQty = 1;
        $this->orderNotes = '';
        $this->serviceDate = today()->toDateString();
        $this->serviceTime = '10:00';
        $this->chargeToFolio = true;
        $this->showOrderForm = true;
    }

    public function cancelOrder(): void { $this->showOrderForm = false; }

    public function placeOrder(): void
    {
        $this->validate([
            'orderAmenityId' => 'required|exists:amenities,id',
            'orderQty' => 'required|integer|min:1',
        ]);
        $ctx = app(TenantContext::class);
        $a = Amenity::findOrFail($this->orderAmenityId);
        $unit = (float) $a->price;
        $qty = max(1, (int) $this->orderQty);
        $sub = $unit * $qty;
        $tax = round($sub * ((float) $a->tax_percent / 100), 2);
        $total = $sub + $tax;

        DB::transaction(function () use ($ctx, $a, $unit, $qty, $sub, $tax, $total) {
            $folioId = null;
            if ($this->chargeToFolio && $this->orderReservationId) {
                $folio = Folio::where('reservation_id', $this->orderReservationId)
                    ->whereIn('status', ['open', 'closed'])
                    ->orderBy('id')->first();
                if (!$folio) {
                    $r = Reservation::find($this->orderReservationId);
                    $folio = Folio::create([
                        'tenant_id' => $ctx->tenantId(),
                        'property_id' => $ctx->propertyId(),
                        'reservation_id' => $this->orderReservationId,
                        'folio_number' => 'F-' . now()->format('ymd') . '-' . $this->orderReservationId,
                        'type' => 'guest',
                        'guest_id' => $r?->guest_id,
                        'billing_name' => $r?->guest_name ?? 'Guest',
                        'currency' => 'INR',
                        'status' => 'open',
                    ]);
                }
                $folioId = $folio->id;

                $cat = match ($a->category) {
                    'spa' => 'spa',
                    'meal' => 'food',
                    default => 'misc',
                };
                FolioCharge::create([
                    'tenant_id' => $ctx->tenantId(),
                    'property_id' => $ctx->propertyId(),
                    'folio_id' => $folio->id,
                    'charge_date' => today()->toDateString(),
                    'charge_time' => now()->toTimeString(),
                    'business_date' => today()->toDateString(),
                    'category' => $cat,
                    'description' => "{$a->name} × {$qty}",
                    'reference' => $a->code,
                    'quantity' => $qty,
                    'rate' => $unit,
                    'amount' => $sub,
                    'discount_amount' => 0,
                    'tax_amount' => $tax,
                    'net_amount' => $total,
                    'tax_breakdown' => ['cgst' => round($tax / 2, 2), 'sgst' => round($tax / 2, 2), 'total' => $tax],
                    'posted_by' => auth()->id(),
                ]);
                $folio->recomputeTotals();
            }

            AmenityOrder::create([
                'tenant_id' => $ctx->tenantId(),
                'property_id' => $ctx->propertyId(),
                'amenity_id' => $a->id,
                'reservation_id' => $this->orderReservationId,
                'folio_id' => $folioId,
                'order_number' => 'AMN-' . now()->format('ymd') . '-' . str_pad((string) (AmenityOrder::count() + 1), 4, '0', STR_PAD_LEFT),
                'service_date' => $this->serviceDate ?: today()->toDateString(),
                'service_time' => $this->serviceTime ?: '10:00',
                'quantity' => $qty,
                'unit_price' => $unit,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'status' => 'confirmed',
                'notes' => $this->orderNotes,
            ]);
        });

        session()->flash('success', "Order placed for {$a->name} · ₹" . number_format($total, 2) . ($this->chargeToFolio && $this->orderReservationId ? ' (charged to folio).' : '.'));
        $this->showOrderForm = false;
    }

    public function fulfillOrder(int $id): void
    {
        $o = AmenityOrder::findOrFail($id);
        $o->update(['status' => 'fulfilled', 'fulfilled_at' => now(), 'fulfilled_by' => auth()->id()]);
        session()->flash('success', "Order {$o->order_number} fulfilled.");
    }

    public function cancelAmenityOrder(int $id): void
    {
        $o = AmenityOrder::findOrFail($id);
        $o->update(['status' => 'cancelled']);
        session()->flash('success', "Order {$o->order_number} cancelled.");
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();

        $amenitiesQ = Amenity::where('property_id', $propertyId);
        if ($this->typeFilter !== '') $amenitiesQ->where('category', $this->typeFilter);
        $amenities = $amenitiesQ->orderBy('name')->get();

        $orders = AmenityOrder::where('property_id', $propertyId)
            ->orderByDesc('created_at')->limit(50)
            ->with(['amenity', 'reservation'])->get();

        $stats = [
            'spa' => Amenity::where('property_id', $propertyId)->where('category', 'spa')->count(),
            'utility' => Amenity::where('property_id', $propertyId)->where('category', 'utility')->count(),
            'transport' => Amenity::where('property_id', $propertyId)->where('category', 'transport')->count(),
            'tour' => Amenity::where('property_id', $propertyId)->where('category', 'tour')->count(),
            'today_orders' => AmenityOrder::where('property_id', $propertyId)->whereDate('service_date', today())->count(),
            'today_revenue' => (float) AmenityOrder::where('property_id', $propertyId)->whereDate('service_date', today())->where('status', '!=', 'cancelled')->sum('total_amount'),
        ];

        $checkedInReservations = Reservation::where('property_id', $propertyId)->where('status', 'checked_in')->orderBy('guest_name')->get();
        $orderingAmenity = $this->orderAmenityId ? Amenity::find($this->orderAmenityId) : null;

        return view('livewire.amenities.amenities-hub', compact(
            'amenities', 'orders', 'stats', 'checkedInReservations', 'orderingAmenity'
        ));
    }
}
