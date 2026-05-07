<?php

namespace App\Livewire\CRM;

use App\Models\Guest;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app-shell')]
class GuestList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $segment = '';

    public bool $showDuplicates = false;
    /** @var array<int,int> map of duplicate-group-key => primaryGuestId */
    public array $primaryChoice = [];

    // Add-guest modal state
    public bool $showAddModal = false;
    public string $salutation = '';
    public string $first_name = '';
    public string $last_name = '';
    public string $gender = '';
    public ?string $dob = null;
    public string $email = '';
    public string $phone = '';
    public string $address = '';
    public string $city = '';
    public string $country = 'IN';
    public string $id_type = '';
    public string $id_number = '';
    public string $new_segment = '';
    public bool $vip = false;
    public bool $is_blacklisted = false;

    public function updating($name)
    {
        if (in_array($name, ['search', 'segment'])) {
            $this->resetPage();
        }
    }

    public function openAdd(): void
    {
        $this->reset([
            'salutation', 'first_name', 'last_name', 'gender', 'dob', 'email',
            'phone', 'address', 'city', 'id_type', 'id_number', 'new_segment',
            'vip', 'is_blacklisted',
        ]);
        $this->country = 'IN';
        $this->resetErrorBag();
        $this->showAddModal = true;
    }

    public function closeAdd(): void
    {
        $this->showAddModal = false;
    }

    public function saveGuest(): void
    {
        $data = $this->validate([
            'salutation'      => 'nullable|string|max:10',
            'first_name'      => 'required|string|max:255',
            'last_name'       => 'nullable|string|max:255',
            'gender'          => 'nullable|in:M,F,O',
            'dob'             => 'nullable|date',
            'email'           => 'nullable|email|max:255',
            'phone'           => 'nullable|string|max:20',
            'address'         => 'nullable|string|max:1000',
            'city'            => 'nullable|string|max:255',
            'country'         => 'nullable|string|max:2',
            'id_type'         => 'nullable|string|max:30',
            'id_number'       => 'nullable|string|max:255',
            'new_segment'     => 'nullable|string|max:30',
            'vip'             => 'boolean',
            'is_blacklisted'  => 'boolean',
        ]);

        $segment = $data['new_segment'] ?: ($this->vip ? 'vip' : null);

        Guest::create([
            'salutation'     => $data['salutation'] ?: null,
            'first_name'     => $data['first_name'],
            'last_name'      => $data['last_name'] ?: null,
            'gender'         => $data['gender'] ?: null,
            'dob'            => $data['dob'] ?: null,
            'email'          => $data['email'] ?: null,
            'phone'          => $data['phone'] ?: null,
            'address'        => $data['address'] ?: null,
            'city'           => $data['city'] ?: null,
            'country'        => $data['country'] ?: 'IN',
            'id_type'        => $data['id_type'] ?: null,
            'id_number'      => $data['id_number'] ?: null,
            'segment'        => $segment,
            'is_blacklisted' => (bool) $data['is_blacklisted'],
        ]);

        $this->showAddModal = false;
        session()->flash('success', "Guest '" . trim($data['first_name'] . ' ' . ($data['last_name'] ?? '')) . "' created.");
        $this->resetPage();
    }

    public function deleteGuest(int $id): void
    {
        $ctx = app(TenantContext::class);
        $guest = Guest::where('tenant_id', $ctx->tenantId())->findOrFail($id);

        $resCount = $guest->reservations()->count();
        if ($resCount > 0) {
            session()->flash('error', "Cannot delete '{$guest->display_name}' — has {$resCount} reservation(s) on file.");
            return;
        }

        $name = $guest->display_name;
        $guest->delete(); // soft-delete via SoftDeletes trait
        session()->flash('success', "Guest '{$name}' deleted.");
        $this->resetPage();
    }

    public function toggleDuplicates(): void
    {
        $this->showDuplicates = !$this->showDuplicates;
    }

    public function mergeDuplicates(int $primaryId, int $secondaryId): void
    {
        if ($primaryId === $secondaryId) {
            session()->flash('error', 'Primary and secondary cannot be the same guest.');
            return;
        }

        $ctx = app(TenantContext::class);
        $primary   = Guest::where('tenant_id', $ctx->tenantId())->findOrFail($primaryId);
        $secondary = Guest::where('tenant_id', $ctx->tenantId())->findOrFail($secondaryId);

        DB::transaction(function () use ($primary, $secondary) {
            // Move reservations
            DB::table('reservations')->where('guest_id', $secondary->id)->update(['guest_id' => $primary->id]);

            // Move banquet bookings (if table exists)
            if (Schema::hasTable('banquet_bookings')) {
                DB::table('banquet_bookings')->where('guest_id', $secondary->id)->update(['guest_id' => $primary->id]);
            }

            // Move payments via reservation_id (payments.guest_id may not exist)
            // Reservations were already remapped; payments tied to them now point at primary.
            // If a payments.guest_id column exists, update it too.
            if (Schema::hasColumn('payments', 'guest_id')) {
                DB::table('payments')->where('guest_id', $secondary->id)->update(['guest_id' => $primary->id]);
            }

            // Move folios (folios.guest_id)
            if (Schema::hasColumn('folios', 'guest_id')) {
                DB::table('folios')->where('guest_id', $secondary->id)->update(['guest_id' => $primary->id]);
            }

            // Merge notes_log entries from secondary's preferences into primary's preferences
            $primaryPrefs   = is_array($primary->preferences) ? $primary->preferences : [];
            $secondaryPrefs = is_array($secondary->preferences) ? $secondary->preferences : [];

            $primaryLog   = is_array($primaryPrefs['notes_log'] ?? null) ? $primaryPrefs['notes_log'] : [];
            $secondaryLog = is_array($secondaryPrefs['notes_log'] ?? null) ? $secondaryPrefs['notes_log'] : [];

            if (!empty($secondaryLog)) {
                // Tag merged-in notes
                foreach ($secondaryLog as &$entry) {
                    if (is_array($entry)) {
                        $entry['merged_from_guest_id'] = $secondary->id;
                    }
                }
                unset($entry);
            }

            $merged = array_merge($primaryLog, $secondaryLog);
            // Sort newest first by 'at' if present
            usort($merged, function ($a, $b) {
                return strcmp((string)($b['at'] ?? ''), (string)($a['at'] ?? ''));
            });

            $primaryPrefs['notes_log'] = $merged;
            $primary->update(['preferences' => $primaryPrefs]);

            // Roll up lifetime stats roughly
            $primary->update([
                'total_visits'   => (int) $primary->total_visits + (int) $secondary->total_visits,
                'lifetime_spend' => (float) $primary->lifetime_spend + (float) $secondary->lifetime_spend,
                'last_stay_date' => $primary->last_stay_date && $secondary->last_stay_date
                    ? ($primary->last_stay_date->gt($secondary->last_stay_date) ? $primary->last_stay_date : $secondary->last_stay_date)
                    : ($primary->last_stay_date ?: $secondary->last_stay_date),
            ]);

            // Soft-delete secondary
            $secondary->delete();
        });

        session()->flash('success', "Merged guest #{$secondary->id} into #{$primary->id}.");
        $this->resetPage();
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $q = Guest::where('tenant_id', $ctx->tenantId())->orderByDesc('last_stay_date');
        if ($this->search !== '') {
            $t = '%' . $this->search . '%';
            $q->where(fn ($q) => $q->where('first_name', 'like', $t)
                ->orWhere('last_name', 'like', $t)
                ->orWhere('phone', 'like', $t)
                ->orWhere('email', 'like', $t));
        }
        if ($this->segment !== '') {
            $q->where('segment', $this->segment);
        }
        $guests = $q->paginate(20);

        $stats = [
            'total'          => Guest::where('tenant_id', $ctx->tenantId())->count(),
            'loyalty'        => Guest::where('tenant_id', $ctx->tenantId())->whereNotNull('loyalty_tier')->count(),
            'corporate'      => Guest::where('tenant_id', $ctx->tenantId())->whereNotNull('company_id')->count(),
            'lifetime_spend' => (float) Guest::where('tenant_id', $ctx->tenantId())->sum('lifetime_spend'),
        ];

        // Reservation counts per guest (only for the page being rendered, plus duplicate candidates)
        $idsOnPage = $guests->pluck('id')->all();
        $reservationCounts = empty($idsOnPage)
            ? collect()
            : DB::table('reservations')
                ->whereIn('guest_id', $idsOnPage)
                ->selectRaw('guest_id, count(*) as c')
                ->groupBy('guest_id')
                ->pluck('c', 'guest_id');

        // Duplicate detection — only when panel open
        $duplicateGroups = [];
        if ($this->showDuplicates) {
            $tenantId = $ctx->tenantId();

            // Group by lowercased email or phone
            $allGuests = Guest::where('tenant_id', $tenantId)
                ->orderByDesc('lifetime_spend')
                ->orderByDesc('total_visits')
                ->get(['id', 'first_name', 'last_name', 'email', 'phone', 'lifetime_spend', 'total_visits', 'last_stay_date', 'created_at']);

            $byEmail = [];
            $byPhone = [];
            foreach ($allGuests as $g) {
                $em = strtolower(trim((string) $g->email));
                $ph = preg_replace('/[^0-9+]/', '', (string) $g->phone);
                if ($em !== '') $byEmail[$em][] = $g;
                if ($ph !== '' && strlen($ph) >= 6) $byPhone[$ph][] = $g;
            }

            $seenPair = [];
            $emit = function (string $key, string $matchType, array $rows) use (&$duplicateGroups, &$seenPair) {
                if (count($rows) < 2) return;
                $idsKey = collect($rows)->pluck('id')->sort()->implode('-');
                if (isset($seenPair[$idsKey])) return;
                $seenPair[$idsKey] = true;
                $duplicateGroups[] = [
                    'key'        => $matchType.':'.$key,
                    'match_type' => $matchType,
                    'match_value'=> $key,
                    'guests'     => $rows,
                ];
            };

            foreach ($byEmail as $email => $rows) { $emit($email, 'email', $rows); }
            foreach ($byPhone as $phone => $rows) { $emit($phone, 'phone', $rows); }

            // Pull reservation counts for any guest appearing in groups
            $allDupIds = collect($duplicateGroups)->flatMap(fn ($g) => collect($g['guests'])->pluck('id'))->unique()->values()->all();
            if (!empty($allDupIds)) {
                $extraCounts = DB::table('reservations')
                    ->whereIn('guest_id', $allDupIds)
                    ->selectRaw('guest_id, count(*) as c')
                    ->groupBy('guest_id')
                    ->pluck('c', 'guest_id');
                foreach ($extraCounts as $gid => $c) {
                    $reservationCounts[$gid] = $c;
                }
            }
        }

        return view('livewire.crm.guest-list', compact('guests', 'stats', 'reservationCounts', 'duplicateGroups'));
    }
}
