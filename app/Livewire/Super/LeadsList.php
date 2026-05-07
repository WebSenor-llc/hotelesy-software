<?php

namespace App\Livewire\Super;

use App\Models\MarketingLead;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app-shell')]
class LeadsList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public ?int $editId = null;

    public ?string $newNote = '';
    public ?string $editStatus = null;
    public ?string $editFollowup = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->is_super_admin, 403);
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }

    public function startEdit(int $id): void
    {
        $lead = app(TenantContext::class)->bypass(fn () => MarketingLead::findOrFail($id));
        $this->editId = $id;
        $this->editStatus = $lead->status;
        $this->editFollowup = $lead->next_followup_at?->format('Y-m-d');
        $this->newNote = '';
    }

    public function cancelEdit(): void
    {
        $this->editId = null;
        $this->newNote = '';
    }

    public function saveEdit(): void
    {
        abort_unless(auth()->user()?->is_super_admin, 403);
        $this->validate([
            'editStatus'   => 'required|in:' . implode(',', array_keys(MarketingLead::STATUSES)),
            'editFollowup' => 'nullable|date',
            'newNote'      => 'nullable|string|max:5000',
        ]);

        app(TenantContext::class)->bypass(function () {
            $lead = MarketingLead::findOrFail($this->editId);
            $existingNotes = $lead->notes ?: '';
            $payload = [
                'status' => $this->editStatus,
                'next_followup_at' => $this->editFollowup ? \Carbon\Carbon::parse($this->editFollowup) : null,
            ];
            if ($this->newNote) {
                $stamp = now()->format('d M Y H:i') . ' (' . (auth()->user()->name ?: 'admin') . ')';
                $payload['notes'] = trim($existingNotes . "\n\n" . $stamp . ":\n" . $this->newNote);
            }
            if ($this->editStatus === 'contacted' && ! $lead->contacted_at) {
                $payload['contacted_at'] = now();
            }
            $lead->update($payload);
        });

        session()->flash('success', 'Lead updated.');
        $this->editId = null;
    }

    public function render()
    {
        return app(TenantContext::class)->bypass(function () {
            $q = MarketingLead::query()->orderByDesc('created_at');
            if ($this->statusFilter !== 'all') $q->where('status', $this->statusFilter);
            if ($s = trim($this->search)) {
                $q->where(function ($x) use ($s) {
                    $x->where('name', 'like', "%$s%")
                      ->orWhere('email', 'like', "%$s%")
                      ->orWhere('phone', 'like', "%$s%")
                      ->orWhere('hotel_name', 'like', "%$s%");
                });
            }

            return view('livewire.super.leads-list', [
                'leads'     => $q->paginate(25),
                'editLead'  => $this->editId ? MarketingLead::find($this->editId) : null,
                'statuses'  => MarketingLead::STATUSES,
                'counts'    => [
                    'all'       => MarketingLead::count(),
                    'new'       => MarketingLead::where('status','new')->count(),
                    'contacted' => MarketingLead::where('status','contacted')->count(),
                    'qualified' => MarketingLead::where('status','qualified')->count(),
                    'won'       => MarketingLead::where('status','won')->count(),
                    'lost'      => MarketingLead::where('status','lost')->count(),
                ],
            ]);
        });
    }
}
