<?php

namespace App\Livewire\Setup;

use App\Models\Room;
use App\Models\RoomType;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class Rooms extends Component
{
    public bool $showForm = false;
    public ?int $editId = null;
    public string $number = ''; public ?int $room_type_id = null;
    public ?int $floor = 1; public string $wing = ''; public string $view = '';
    public string $status = 'vacant_clean'; public string $fo_status = 'vacant';
    public bool $is_smoking = false; public bool $is_accessible = false;
    public bool $is_active = true;
    public string $filter = '';

    // Bulk create form
    public bool $showBulkForm = false;
    public ?int $bulk_floor = 1;
    public string $bulk_wing = '';
    public ?int $bulk_room_type_id = null;
    public string $bulk_prefix = '1';
    public int $bulk_start = 1;
    public int $bulk_count = 10;
    public string $bulk_status = 'vacant_clean';

    public function startCreate(): void
    {
        $this->reset(['editId','number','wing','view','room_type_id','is_smoking','is_accessible']);
        $this->floor = 1;
        $this->status = 'vacant_clean';
        $this->fo_status = 'vacant';
        $this->is_active = true;
        $this->showForm = true;
        $this->showBulkForm = false;
    }

    public function cancelForm(): void
    {
        $this->reset(['editId','number','wing','view','room_type_id','is_smoking','is_accessible']);
        $this->showForm = false;
    }

    public function startEdit(int $id): void
    {
        $r = Room::findOrFail($id);
        $this->editId = $id;
        foreach (['number','room_type_id','floor','wing','view','status','fo_status','is_smoking','is_accessible','is_active'] as $f) {
            $this->$f = $r->$f;
        }
        $this->showForm = true;
        $this->showBulkForm = false;
    }

    public function save(): void
    {
        $data = $this->validate([
            'number'=>'required|string|max:20','room_type_id'=>'required|exists:room_types,id',
            'floor'=>'integer','wing'=>'nullable|string|max:30','view'=>'nullable|string|max:50',
            'status'=>'required','fo_status'=>'required',
            'is_smoking'=>'boolean','is_accessible'=>'boolean','is_active'=>'boolean',
        ]);
        $data['property_id'] = app(TenantContext::class)->propertyId();
        if ($this->editId) Room::findOrFail($this->editId)->update($data); else Room::create($data);
        session()->flash('success','Room saved.');
        $this->reset(['editId','number','wing','view','room_type_id','is_smoking','is_accessible']);
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        Room::findOrFail($id)->delete();
        session()->flash('success','Room deleted.');
    }

    public function startBulkCreate(): void
    {
        $this->reset(['bulk_wing','bulk_room_type_id']);
        $this->bulk_floor = 1;
        $this->bulk_prefix = '1';
        $this->bulk_start = 1;
        $this->bulk_count = 10;
        $this->bulk_status = 'vacant_clean';
        $this->showBulkForm = true;
        $this->showForm = false;
    }

    public function cancelBulk(): void
    {
        $this->showBulkForm = false;
    }

    public function bulkSave(): void
    {
        $data = $this->validate([
            'bulk_floor' => 'required|integer|min:0|max:100',
            'bulk_wing' => 'nullable|string|max:30',
            'bulk_room_type_id' => 'required|exists:room_types,id',
            'bulk_prefix' => 'nullable|string|max:5',
            'bulk_start' => 'required|integer|min:0|max:9999',
            'bulk_count' => 'required|integer|min:1|max:200',
            'bulk_status' => 'required',
        ]);

        $propertyId = app(TenantContext::class)->propertyId();
        $created = 0; $skipped = 0;

        DB::transaction(function () use ($data, $propertyId, &$created, &$skipped) {
            for ($i = 0; $i < $data['bulk_count']; $i++) {
                $num = $data['bulk_prefix'] . str_pad((string)($data['bulk_start'] + $i), 2, '0', STR_PAD_LEFT);
                $exists = Room::where('property_id', $propertyId)->where('number', $num)->exists();
                if ($exists) { $skipped++; continue; }
                Room::create([
                    'property_id' => $propertyId,
                    'number' => $num,
                    'room_type_id' => $data['bulk_room_type_id'],
                    'floor' => $data['bulk_floor'],
                    'wing' => $data['bulk_wing'] ?: null,
                    'status' => $data['bulk_status'],
                    'fo_status' => 'vacant',
                    'is_active' => true,
                ]);
                $created++;
            }
        });

        $msg = "Created {$created} room(s)";
        if ($skipped > 0) $msg .= ", skipped {$skipped} duplicate(s)";
        session()->flash('success', $msg . '.');
        $this->showBulkForm = false;
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $q = Room::where('property_id', $ctx->propertyId())->with('roomType');
        if ($this->filter !== '') $q->where('number', 'like', '%'.$this->filter.'%');
        $rooms = $q->orderBy('floor')->orderBy('number')->get();
        $roomTypes = RoomType::where('property_id', $ctx->propertyId())->where('is_active', true)->get();
        return view('livewire.setup.rooms', compact('rooms','roomTypes'));
    }
}
