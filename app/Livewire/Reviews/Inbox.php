<?php

namespace App\Livewire\Reviews;

use App\Models\Reservation;
use App\Models\Reviews\Review;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app-shell')]
class Inbox extends Component
{
    use WithPagination;

    public string $sourceFilter = '';
    public string $sentimentFilter = '';

    public ?int $replyingToId = null;
    public string $replyText = '';

    // Manual review form
    public bool $showAddForm = false;
    public string $newSource = 'direct';
    public string $newReviewerName = '';
    public float $newRating = 5.0;
    public string $newTitle = '';
    public string $newBody = '';
    public ?int $newReservationId = null;
    public string $newReservationSearch = '';
    public string $newPostedAt = '';

    public function startAddReview(): void
    {
        $this->resetAddForm();
        $this->showAddForm = true;
    }

    public function cancelAddReview(): void
    {
        $this->showAddForm = false;
        $this->resetAddForm();
    }

    private function resetAddForm(): void
    {
        $this->newSource = 'direct';
        $this->newReviewerName = '';
        $this->newRating = 5.0;
        $this->newTitle = '';
        $this->newBody = '';
        $this->newReservationId = null;
        $this->newReservationSearch = '';
        $this->newPostedAt = today()->toDateString();
    }

    public function pickReservation(int $id, string $label): void
    {
        $this->newReservationId = $id;
        $this->newReservationSearch = $label;
    }

    public function clearReservation(): void
    {
        $this->newReservationId = null;
        $this->newReservationSearch = '';
    }

    public function saveManualReview(): void
    {
        $data = $this->validate([
            'newSource' => 'required|in:booking_com,mmt,tripadvisor,google,direct,goibibo,agoda,expedia,in_house,other',
            'newReviewerName' => 'nullable|string|max:255',
            'newRating' => 'required|numeric|min:1|max:5',
            'newTitle' => 'nullable|string|max:255',
            'newBody' => 'required|string|max:5000',
            'newReservationId' => 'nullable|integer|exists:reservations,id',
            'newPostedAt' => 'required|date',
        ]);

        $ctx = app(TenantContext::class);
        Review::create([
            'tenant_id'    => $ctx->tenantId(),
            'property_id'  => $ctx->propertyId(),
            'source'       => $data['newSource'],
            'reservation_id' => $data['newReservationId'] ?: null,
            'reviewer_name'=> $data['newReviewerName'] ?: null,
            'review_date'  => $data['newPostedAt'],
            'rating'       => $data['newRating'],
            'title'        => $data['newTitle'] ?: null,
            'body'         => $data['newBody'],
            'language'     => 'en',
            'is_visible'   => true,
            'is_flagged'   => false,
            'sentiment'    => $data['newRating'] >= 4 ? 'positive' : ($data['newRating'] >= 3 ? 'neutral' : 'negative'),
        ]);

        session()->flash('success', 'Review added.');
        $this->showAddForm = false;
        $this->resetAddForm();
        $this->resetPage();
    }

    public function startReply(int $id): void
    {
        $this->replyingToId = $id;
        $existing = Review::find($id);
        $this->replyText = $existing?->response_text ?? '';
    }

    public function cancelReply(): void
    {
        $this->replyingToId = null;
        $this->replyText = '';
    }

    public function saveReply(): void
    {
        $this->validate([
            'replyingToId' => 'required|integer',
            'replyText' => 'required|string|min:2|max:5000',
        ]);

        $review = Review::findOrFail($this->replyingToId);
        $review->update([
            'response_text' => $this->replyText,
            'responded_at' => now(),
            'responded_by' => auth()->id(),
        ]);

        $this->cancelReply();
        session()->flash('success', 'Response saved.');
    }

    public function mount(): void
    {
        $this->newPostedAt = today()->toDateString();
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();

        $modelClass = '\\App\\Models\\Reviews\\Review';
        if (!class_exists($modelClass)) {
            return view('livewire.reviews.inbox-empty');
        }

        $query = Review::where('property_id', $propertyId)->orderByDesc('review_date');
        if ($this->sourceFilter !== '') $query->where('source', $this->sourceFilter);
        if ($this->sentimentFilter !== '') $query->where('sentiment', $this->sentimentFilter);

        $reviews = $query->paginate(15);

        $stats = [
            'total'    => Review::where('property_id', $propertyId)->count(),
            'avgRating'=> round(Review::where('property_id', $propertyId)->avg('rating') ?? 0, 1),
            'unreplied'=> Review::where('property_id', $propertyId)->whereNull('responded_at')->count(),
        ];

        $sources = Review::where('property_id', $propertyId)->distinct()->pluck('source');

        // Reservation autocomplete suggestions for the manual-add form
        $reservationSuggestions = collect();
        if ($this->showAddForm && trim($this->newReservationSearch) !== '' && !$this->newReservationId) {
            $term = '%' . trim($this->newReservationSearch) . '%';
            $reservationSuggestions = Reservation::where('property_id', $propertyId)
                ->where(function ($q) use ($term) {
                    $q->where('reservation_number', 'like', $term)
                      ->orWhere('confirmation_number', 'like', $term)
                      ->orWhere('guest_name', 'like', $term);
                })
                ->orderByDesc('arrival_date')
                ->limit(8)
                ->get(['id', 'reservation_number', 'guest_name', 'arrival_date']);
        }

        return view('livewire.reviews.inbox', compact('reviews','stats','sources','reservationSuggestions'));
    }
}
