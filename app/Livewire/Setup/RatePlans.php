<?php

namespace App\Livewire\Setup;

use App\Models\RatePlan;
use App\Models\RoomType;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class RatePlans extends Component
{
    public ?int $editId = null;
    public bool $showForm = false;
    public ?int $room_type_id = null;
    public string $code = '';
    public string $name = '';
    public string $meal_plan = 'CP';
    public string $pricing_mode = 'fixed';
    public string $tax_mode = 'exclusive';
    public float $base_rate = 0;
    public float $rate_modifier = 0;
    public int $min_stay = 1;
    public int $max_stay = 0;
    public int $advance_booking_days = 0;
    public bool $refundable = true;
    public int $cancellation_hours = 24;
    public float $cancellation_charge_percent = 0;
    public bool $is_corporate = false;
    public bool $is_promotional = false;
    public bool $is_active = true;
    public bool $sell_on_channels = true;

    public function startCreate(): void
    {
        $this->reset();
        $this->meal_plan = 'CP'; $this->pricing_mode = 'fixed'; $this->tax_mode = 'exclusive'; $this->min_stay = 1;
        $this->cancellation_hours = 24; $this->refundable = true;
        $this->is_active = true; $this->sell_on_channels = true;
        $this->showForm = true;
    }

    public function startEdit(int $id): void
    {
        $r = RatePlan::findOrFail($id);
        $this->editId = $id;
        foreach (['room_type_id','code','name','meal_plan','pricing_mode','tax_mode','base_rate','rate_modifier','min_stay','max_stay','advance_booking_days','refundable','cancellation_hours','cancellation_charge_percent','is_corporate','is_promotional','is_active','sell_on_channels'] as $f) {
            $this->$f = $r->$f;
        }
        $this->showForm = true;
    }

    public function cancelForm(): void { $this->showForm = false; $this->reset(['editId']); }

    public function save(): void
    {
        $data = $this->validate([
            'room_type_id' => 'required|exists:room_types,id',
            'code' => 'required|string|max:30',
            'name' => 'required|string|max:255',
            'meal_plan' => 'required|in:EP,CP,MAP,AP',
            'pricing_mode' => 'required|in:fixed,percentage_of_base,amount_off_base,amount_added',
            'tax_mode' => 'required|in:inclusive,exclusive',
            'base_rate' => 'numeric|min:0',
            'rate_modifier' => 'numeric',
            'min_stay' => 'integer|min:1',
            'max_stay' => 'integer|min:0',
            'advance_booking_days' => 'integer|min:0',
            'cancellation_hours' => 'integer|min:0',
            'cancellation_charge_percent' => 'numeric|min:0|max:100',
            'refundable' => 'boolean','is_corporate'=>'boolean','is_promotional'=>'boolean','is_active'=>'boolean','sell_on_channels'=>'boolean',
        ]);
        $ctx = app(TenantContext::class);
        $data['property_id'] = $ctx->propertyId();

        if ($this->editId) {
            RatePlan::findOrFail($this->editId)->update($data);
        } else {
            RatePlan::create($data);
        }
        session()->flash('success', 'Rate plan saved.');
        $this->reset(['editId']);
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        RatePlan::findOrFail($id)->delete();
        session()->flash('success', 'Rate plan deleted.');
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $ratePlans = RatePlan::where('property_id', $ctx->propertyId())->with('roomType')->orderBy('code')->get();
        $roomTypes = RoomType::where('property_id', $ctx->propertyId())->where('is_active', true)->get();
        return view('livewire.setup.rate-plans', compact('ratePlans','roomTypes'));
    }
}
