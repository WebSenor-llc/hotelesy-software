<?php

namespace App\Livewire\Setup;

use App\Models\RoomType;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app-shell')]
class RoomTypes extends Component
{
    use WithFileUploads;

    public ?int $editId = null;
    public bool $showForm = false;
    public string $code = '';
    public string $name = '';
    public string $description = '';
    public int $base_occupancy = 2;
    public int $max_occupancy = 3;
    public int $max_adults = 2;
    public int $max_children = 2;
    public int $extra_bed_capacity = 1;
    public float $base_rate = 0;
    public float $extra_adult_rate = 0;
    public float $extra_child_rate = 0;
    public float $extra_bed_rate = 0;
    public string $bed_type = 'king';
    public ?float $size_sqft = null;
    public bool $is_active = true;
    public bool $sell_on_channels = true;
    public bool $allow_overbook = false;
    public int $overbook_limit = 0;

    /** Existing photos (storage paths under public disk) for the room type being edited. */
    public array $existingPhotos = [];

    /** Newly uploaded files staged for save. */
    public array $newPhotos = [];

    public function startCreate(): void
    {
        $this->reset(['editId','code','name','description','size_sqft','existingPhotos','newPhotos']);
        $this->base_occupancy = 2; $this->max_occupancy = 3; $this->max_adults = 2; $this->max_children = 2;
        $this->extra_bed_capacity = 1; $this->base_rate = 0; $this->extra_adult_rate = 0;
        $this->extra_child_rate = 0; $this->extra_bed_rate = 0; $this->bed_type = 'king';
        $this->is_active = true; $this->sell_on_channels = true;
        $this->allow_overbook = false; $this->overbook_limit = 0;
        $this->showForm = true;
    }

    public function startEdit(int $id): void
    {
        $rt = RoomType::findOrFail($id);
        $this->editId = $id;
        foreach (['code','name','description','base_occupancy','max_occupancy','max_adults','max_children','extra_bed_capacity','base_rate','extra_adult_rate','extra_child_rate','extra_bed_rate','bed_type','size_sqft','is_active','sell_on_channels','allow_overbook','overbook_limit'] as $f) {
            $this->$f = $rt->$f;
        }
        $this->allow_overbook = (bool) $rt->allow_overbook;
        $this->overbook_limit = (int) $rt->overbook_limit;
        $this->existingPhotos = is_array($rt->photos) ? array_values($rt->photos) : [];
        $this->newPhotos = [];
        $this->showForm = true;
    }

    public function cancelForm(): void { $this->showForm = false; $this->reset(['editId','existingPhotos','newPhotos']); }

    /**
     * Remove a photo from the existing list (also deletes the file on disk).
     */
    public function removeExistingPhoto(int $index): void
    {
        if (!isset($this->existingPhotos[$index])) return;
        $path = $this->existingPhotos[$index];
        if (is_string($path) && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
        unset($this->existingPhotos[$index]);
        $this->existingPhotos = array_values($this->existingPhotos);
    }

    /**
     * Drop a not-yet-saved upload from the staged list.
     */
    public function removeNewPhoto(int $index): void
    {
        if (!isset($this->newPhotos[$index])) return;
        unset($this->newPhotos[$index]);
        $this->newPhotos = array_values($this->newPhotos);
    }

    public function save(): void
    {
        $data = $this->validate([
            'code' => 'required|string|max:20',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'base_occupancy' => 'required|integer|min:1|max:10',
            'max_occupancy' => 'required|integer|min:1|max:20',
            'max_adults' => 'integer|min:1|max:10',
            'max_children' => 'integer|min:0|max:10',
            'extra_bed_capacity' => 'integer|min:0|max:5',
            'base_rate' => 'required|numeric|min:0',
            'extra_adult_rate' => 'numeric|min:0',
            'extra_child_rate' => 'numeric|min:0',
            'extra_bed_rate' => 'numeric|min:0',
            'bed_type' => 'nullable|string|max:30',
            'size_sqft' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'sell_on_channels' => 'boolean',
            'allow_overbook' => 'boolean',
            'overbook_limit' => 'integer|min:0|max:50',
            'newPhotos.*' => 'image|max:5120', // 5MB per image
        ]);

        // Strip the photo upload bag from $data before mass-assignment.
        unset($data['newPhotos']);

        $ctx = app(TenantContext::class);
        $data['property_id'] = $ctx->propertyId();

        // Persist newly uploaded files to storage/app/public/room-types/
        $storedPaths = [];
        foreach ($this->newPhotos as $file) {
            if (!$file) continue;
            $storedPaths[] = $file->store('room-types', 'public');
        }
        $data['photos'] = array_values(array_merge($this->existingPhotos, $storedPaths));

        if ($this->editId) {
            RoomType::findOrFail($this->editId)->update($data);
            session()->flash('success', "Room type '{$data['name']}' updated.");
        } else {
            RoomType::create($data);
            session()->flash('success', "Room type '{$data['name']}' created.");
        }
        $this->reset(['editId','existingPhotos','newPhotos']);
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        $rt = RoomType::findOrFail($id);
        $name = $rt->name;
        $rt->delete();
        session()->flash('success', "Room type '$name' deleted.");
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $roomTypes = RoomType::where('property_id', $ctx->propertyId())
            ->withCount('rooms')->orderBy('base_rate')->get();
        return view('livewire.setup.room-types', compact('roomTypes'));
    }
}
