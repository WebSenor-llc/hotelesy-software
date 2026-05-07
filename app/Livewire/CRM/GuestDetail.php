<?php

namespace App\Livewire\CRM;

use App\Models\Amenities\AmenityOrder;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app-shell')]
class GuestDetail extends Component
{
    use WithFileUploads;

    /** @var Guest */
    public $guest; // intentionally untyped: typed `Guest` collides with Livewire 4's
                   // implicit-route-binding for the {guest} param and short-circuits mount().

    // UI panels
    public bool $editingProfile = false;
    public bool $editingPrefs = false;

    // Profile edit fields
    public string $salutation = '';
    public string $first_name = '';
    public string $last_name = '';
    public string $gender = '';
    public ?string $dob = null;
    public string $email = '';
    public string $phone = '';
    public string $address = '';
    public string $city = '';
    public string $state = '';
    public string $postal_code = '';
    public string $country = 'IN';
    public string $id_type = '';
    public string $id_number = '';
    public string $segment = '';
    public string $loyalty_tier = '';
    public string $loyalty_number = '';

    // Preferences
    public string $pref_smoking = '';     // smoking | non_smoking | ''
    public string $pref_floor = '';       // low | high | any
    public string $pref_pillow = '';      // soft | firm | feather | hypoallergenic
    public string $pref_dietary = '';     // veg | non_veg | vegan | jain | gluten_free
    public string $pref_bed = '';         // king | twin | etc.
    public string $pref_allergies = '';
    public string $pref_other = '';

    // ID proof / FRRO compliance
    public array $newIdUploads = [];
    public bool $is_foreign_national = false;
    public string $nationality = 'IN';
    public string $passport_number = '';
    public ?string $passport_expiry = null;
    public string $visa_number = '';
    public ?string $visa_expiry = null;
    public string $arrival_from_country = '';
    public ?string $arrival_date_in_india = null;
    public string $next_destination_country = '';
    public string $next_destination_address = '';

    // Add-note form
    public string $newNote = '';

    // Activity tab
    public string $activeTab = 'overview'; // overview | activity
    public string $activitySearch = '';

    public function mount($guest): void
    {
        // Livewire 4 implicit binding may pass either a Guest model OR an id
        // depending on how the property is typed. Normalise to an integer id
        // and re-fetch under explicit tenant scope so the BelongsToTenant
        // global scope doesn't 404 us before we can show a friendly error.
        $guestId = $guest instanceof Guest ? $guest->id : (int) $guest;
        $ctx = app(TenantContext::class);

        $loaded = $ctx->bypass(function () use ($ctx, $guestId) {
            $tenantId = $ctx->tenantId();
            $q = Guest::query();
            // Super admins or unset tenant: don't filter
            if ($tenantId && ! auth()->user()?->is_super_admin) {
                $q->where('tenant_id', $tenantId);
            }
            return $q->where('id', $guestId)->first();
        });

        if (! $loaded) {
            // Friendly 404 with context for debugging
            abort(404, "Guest #{$guestId} not found for the current tenant. "
                . "If this guest exists in another property, switch property first.");
        }

        $this->guest = $loaded;
        $this->hydrateProfileFields();
        $this->hydratePrefFields();
    }

    private function hydrateProfileFields(): void
    {
        $g = $this->guest;
        $this->salutation     = (string) ($g->salutation ?? '');
        $this->first_name     = (string) ($g->first_name ?? '');
        $this->last_name      = (string) ($g->last_name ?? '');
        $this->gender         = (string) ($g->gender ?? '');
        $this->dob            = $g->dob ? $g->dob->format('Y-m-d') : null;
        $this->email          = (string) ($g->email ?? '');
        $this->phone          = (string) ($g->phone ?? '');
        $this->address        = (string) ($g->address ?? '');
        $this->city           = (string) ($g->city ?? '');
        $this->state          = (string) ($g->state ?? '');
        $this->postal_code    = (string) ($g->postal_code ?? '');
        $this->country        = (string) ($g->country ?? 'IN');
        $this->id_type        = (string) ($g->id_type ?? '');
        $this->id_number      = (string) ($g->id_number ?? '');
        $this->segment        = (string) ($g->segment ?? '');
        $this->loyalty_tier   = (string) ($g->loyalty_tier ?? '');
        $this->loyalty_number = (string) ($g->loyalty_number ?? '');

        // FRRO / foreign-national hydration
        $this->is_foreign_national      = (bool) ($g->is_foreign_national ?? false);
        $this->nationality              = (string) ($g->nationality ?? 'IN');
        $this->passport_number          = (string) ($g->passport_number ?? '');
        $this->passport_expiry          = $g->passport_expiry ? $g->passport_expiry->format('Y-m-d') : null;
        $this->visa_number              = (string) ($g->visa_number ?? '');
        $this->visa_expiry              = $g->visa_expiry ? $g->visa_expiry->format('Y-m-d') : null;
        $this->arrival_from_country     = (string) ($g->arrival_from_country ?? '');
        $this->arrival_date_in_india    = $g->arrival_date_in_india ? $g->arrival_date_in_india->format('Y-m-d') : null;
        $this->next_destination_country = (string) ($g->next_destination_country ?? '');
        $this->next_destination_address = (string) ($g->next_destination ?? '');
    }

    public function uploadIdProofs(): void
    {
        $this->validate([
            'newIdUploads.*' => 'file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if (empty($this->newIdUploads)) {
            session()->flash('error', 'No files selected.');
            return;
        }

        $existing = is_array($this->guest->id_proof_files) ? $this->guest->id_proof_files : [];
        foreach ($this->newIdUploads as $upload) {
            if ($upload) {
                $path = $upload->store("public/id-proofs/{$this->guest->id}");
                $existing[] = str_replace('public/', '', $path);
            }
        }

        $this->guest->update(['id_proof_files' => array_values(array_unique($existing))]);
        $this->guest->refresh();
        $this->newIdUploads = [];
        session()->flash('success', 'ID proof file(s) uploaded.');
    }

    public function deleteIdProof(int $index): void
    {
        $files = is_array($this->guest->id_proof_files) ? $this->guest->id_proof_files : [];
        if (!isset($files[$index])) {
            return;
        }
        $path = $files[$index];

        try {
            Storage::disk('public')->delete($path);
        } catch (\Throwable $e) {
            // Ignore filesystem failures; metadata removal still useful.
        }

        unset($files[$index]);
        $this->guest->update(['id_proof_files' => array_values($files)]);
        $this->guest->refresh();
        session()->flash('success', 'ID proof file removed.');
    }

    public function saveForeignNational(): void
    {
        $data = $this->validate([
            'is_foreign_national'      => 'boolean',
            'nationality'              => 'nullable|string|max:2',
            'passport_number'          => 'nullable|string|max:30',
            'passport_expiry'          => 'nullable|date',
            'visa_number'              => 'nullable|string|max:30',
            'visa_expiry'              => 'nullable|date',
            'arrival_from_country'     => 'nullable|string|max:2',
            'arrival_date_in_india'    => 'nullable|date',
            'next_destination_country' => 'nullable|string|max:2',
            'next_destination_address' => 'nullable|string|max:255',
        ]);

        $this->guest->update([
            'is_foreign_national'      => (bool) $data['is_foreign_national'],
            'nationality'              => $data['nationality'] ?: $this->guest->nationality ?: 'IN',
            'passport_number'          => $data['passport_number'] ?: null,
            'passport_expiry'          => $data['passport_expiry'] ?: null,
            'visa_number'              => $data['visa_number'] ?: null,
            'visa_expiry'              => $data['visa_expiry'] ?: null,
            'arrival_from_country'     => $data['arrival_from_country'] ?: null,
            'arrival_date_in_india'    => $data['arrival_date_in_india'] ?: null,
            'next_destination_country' => $data['next_destination_country'] ?: null,
            'next_destination'         => $data['next_destination_address'] ?: null,
        ]);
        $this->guest->refresh();
        session()->flash('success', 'Foreign-national / FRRO details saved.');
    }

    private function hydratePrefFields(): void
    {
        $prefs = is_array($this->guest->preferences) ? $this->guest->preferences : [];
        $this->pref_smoking   = (string) ($prefs['smoking'] ?? '');
        $this->pref_floor     = (string) ($prefs['floor'] ?? '');
        $this->pref_pillow    = (string) ($prefs['pillow'] ?? '');
        $this->pref_dietary   = (string) ($prefs['dietary'] ?? '');
        $this->pref_bed       = (string) ($prefs['bed'] ?? '');
        $this->pref_allergies = (string) ($prefs['allergies'] ?? '');
        $this->pref_other     = (string) ($prefs['other'] ?? '');
    }

    public function startEditProfile(): void
    {
        $this->hydrateProfileFields();
        $this->resetErrorBag();
        $this->editingProfile = true;
    }

    public function cancelEditProfile(): void
    {
        $this->editingProfile = false;
    }

    public function saveProfile(): void
    {
        $data = $this->validate([
            'salutation'     => 'nullable|string|max:10',
            'first_name'     => 'required|string|max:255',
            'last_name'      => 'nullable|string|max:255',
            'gender'         => 'nullable|in:M,F,O',
            'dob'            => 'nullable|date',
            'email'          => 'nullable|email|max:255',
            'phone'          => 'nullable|string|max:20',
            'address'        => 'nullable|string|max:1000',
            'city'           => 'nullable|string|max:255',
            'state'          => 'nullable|string|max:255',
            'postal_code'    => 'nullable|string|max:20',
            'country'        => 'nullable|string|max:2',
            'id_type'        => 'nullable|string|max:30',
            'id_number'      => 'nullable|string|max:255',
            'segment'        => 'nullable|string|max:30',
            'loyalty_tier'   => 'nullable|string|max:50',
            'loyalty_number' => 'nullable|string|max:50',
        ]);

        // Convert empty strings to null for cleanliness
        foreach ($data as $k => $v) {
            if ($v === '') {
                $data[$k] = null;
            }
        }

        $this->guest->update($data);
        $this->guest->refresh();
        $this->editingProfile = false;
        session()->flash('success', 'Profile updated.');
    }

    public function startEditPrefs(): void
    {
        $this->hydratePrefFields();
        $this->editingPrefs = true;
    }

    public function cancelEditPrefs(): void
    {
        $this->editingPrefs = false;
    }

    public function savePrefs(): void
    {
        $existing = is_array($this->guest->preferences) ? $this->guest->preferences : [];

        $payload = [
            'smoking'   => $this->pref_smoking ?: null,
            'floor'     => $this->pref_floor ?: null,
            'pillow'    => $this->pref_pillow ?: null,
            'dietary'   => $this->pref_dietary ?: null,
            'bed'       => $this->pref_bed ?: null,
            'allergies' => $this->pref_allergies ?: null,
            'other'     => $this->pref_other ?: null,
        ];
        $payload = array_filter($payload, fn ($v) => $v !== null && $v !== '');

        // Preserve any existing keys we don't manage (e.g. notes_log)
        $merged = array_merge($existing, $payload);

        // Remove keys we manage but that are now empty
        foreach (['smoking', 'floor', 'pillow', 'dietary', 'bed', 'allergies', 'other'] as $k) {
            if (!array_key_exists($k, $payload) && array_key_exists($k, $merged)) {
                unset($merged[$k]);
            }
        }

        $this->guest->update(['preferences' => $merged]);
        $this->guest->refresh();
        $this->editingPrefs = false;
        session()->flash('success', 'Preferences updated.');
    }

    public function addNote(): void
    {
        $data = $this->validate([
            'newNote' => 'required|string|max:2000',
        ]);

        $prefs = is_array($this->guest->preferences) ? $this->guest->preferences : [];
        $log = $prefs['notes_log'] ?? [];
        if (!is_array($log)) {
            $log = [];
        }

        $log[] = [
            'at'   => now()->toIso8601String(),
            'by'   => auth()->user()?->name ?? auth()->user()?->email ?? 'system',
            'body' => trim($data['newNote']),
        ];
        $prefs['notes_log'] = $log;

        $this->guest->update(['preferences' => $prefs]);
        $this->guest->refresh();
        $this->newNote = '';
        session()->flash('success', 'Note added.');
    }

    public function toggleVip(): void
    {
        $newSegment = $this->guest->segment === 'vip' ? null : 'vip';
        $this->guest->update(['segment' => $newSegment]);
        $this->guest->refresh();
        $this->segment = (string) ($this->guest->segment ?? '');
        session()->flash('success', $newSegment ? 'Marked as VIP.' : 'VIP removed.');
    }

    public function toggleBlacklist(): void
    {
        $now = !$this->guest->is_blacklisted;
        $this->guest->update(['is_blacklisted' => $now]);
        $this->guest->refresh();
        session()->flash('success', $now ? 'Guest blacklisted.' : 'Guest removed from blacklist.');
    }

    public function render()
    {
        $stays = Reservation::where('guest_id', $this->guest->id)
            ->orderByDesc('arrival_date')
            ->get();

        $prefs = is_array($this->guest->preferences) ? $this->guest->preferences : [];
        $notesLog = $prefs['notes_log'] ?? [];
        if (!is_array($notesLog)) {
            $notesLog = [];
        }
        $notesLog = array_reverse($notesLog); // newest first

        $structuredPrefs = collect($prefs)
            ->except(['notes_log', 'note'])
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->all();

        // Build unified activity timeline (newest first)
        $activity = [];
        foreach ($notesLog as $n) {
            $activity[] = [
                'type'  => 'note',
                'at'    => isset($n['at']) ? \Carbon\Carbon::parse($n['at']) : now(),
                'title' => 'Note added',
                'body'  => $n['body'] ?? '',
                'by'    => $n['by'] ?? '—',
            ];
        }
        foreach ($stays as $s) {
            $activity[] = [
                'type'  => 'booking',
                'at'    => $s->created_at ?: \Carbon\Carbon::parse($s->arrival_date),
                'title' => 'Reservation '.$s->reservation_number,
                'body'  => \Carbon\Carbon::parse($s->arrival_date)->format('d M Y').' – '.\Carbon\Carbon::parse($s->departure_date)->format('d M Y').' · '.$s->nights.'N · '.str_replace('_',' ', $s->status).' · ₹'.number_format($s->total_amount ?? 0, 0),
                'by'    => '—',
                'ref'   => $s->reservation_number,
            ];
        }

        $reservationIds = $stays->pluck('id')->all();
        if (!empty($reservationIds)) {
            $payments = Payment::whereIn('reservation_id', $reservationIds)->get();
            foreach ($payments as $p) {
                $activity[] = [
                    'type'  => 'payment',
                    'at'    => $p->created_at ?: ($p->payment_date ? \Carbon\Carbon::parse($p->payment_date) : now()),
                    'title' => 'Payment '.($p->receipt_number ?? '#'.$p->id),
                    'body'  => '₹'.number_format($p->amount ?? 0, 2).' via '.str_replace('_',' ', $p->mode ?? '—').' · '.($p->status ?? ''),
                    'by'    => '—',
                ];
            }

            if (Schema::hasTable('amenity_orders')) {
                $amenities = AmenityOrder::whereIn('reservation_id', $reservationIds)->with('amenity')->get();
                foreach ($amenities as $a) {
                    $activity[] = [
                        'type'  => 'amenity',
                        'at'    => $a->created_at ?: now(),
                        'title' => 'Amenity order',
                        'body'  => ($a->amenity?->name ?? '#'.$a->id).' × '.($a->quantity ?? 1).' · ₹'.number_format($a->total_amount ?? 0, 2).' · '.($a->status ?? ''),
                        'by'    => '—',
                    ];
                }
            }
        }

        // Sort newest first
        usort($activity, fn ($a, $b) => $b['at']->timestamp <=> $a['at']->timestamp);

        // Filter by search text if any
        if (trim($this->activitySearch) !== '') {
            $needle = mb_strtolower(trim($this->activitySearch));
            $activity = array_values(array_filter($activity, function ($e) use ($needle) {
                $hay = mb_strtolower(($e['title'] ?? '').' '.($e['body'] ?? '').' '.($e['by'] ?? '').' '.($e['ref'] ?? ''));
                return str_contains($hay, $needle);
            }));
        }

        return view('livewire.crm.guest-detail', compact('stays', 'notesLog', 'structuredPrefs', 'activity'));
    }
}
