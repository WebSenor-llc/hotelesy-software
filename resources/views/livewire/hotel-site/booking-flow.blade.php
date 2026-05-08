<div>
<section class="pt-32 pb-12 text-center bg-cream">
    <div class="text-xs uppercase tracking-widest text-brass-deep font-semibold mb-4 flex items-center justify-center gap-3">
        <span class="gold-divider"></span> Reserve <span class="gold-divider"></span>
    </div>
    <h1 class="font-serif text-6xl leading-[1.05]">Book your stay</h1>

    {{-- Stay summary --}}
    <div class="mt-8 inline-flex flex-wrap items-center gap-6 px-8 py-4 bg-cream-soft border border-line text-sm text-ink-soft">
        <span><strong class="text-ink">{{ \Carbon\Carbon::parse($checkIn)->format('d M') }}</strong> → <strong class="text-ink">{{ \Carbon\Carbon::parse($checkOut)->format('d M Y') }}</strong></span>
        <span class="w-px h-4 bg-line"></span>
        <span>{{ $nights }} night{{ $nights > 1 ? 's' : '' }}</span>
        <span class="w-px h-4 bg-line"></span>
        <span>{{ $adults }} adult{{ $adults > 1 ? 's' : '' }}@if($children > 0), {{ $children }} child{{ $children > 1 ? 'ren' : '' }}@endif · {{ $roomsCount }} room{{ $roomsCount > 1 ? 's' : '' }}</span>
    </div>
</section>

@if($step === 'rooms')
<section class="max-w-7xl mx-auto px-6 py-12">
    @if($roomTypes->isEmpty())
        <div class="text-center text-ink-mute py-20">No rooms configured yet.</div>
    @else
        @php
            $stockImages = [
                'https://images.unsplash.com/photo-1566665797739-1674de7a421a?w=800&q=80&auto=format&fit=crop',
                'https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=800&q=80&auto=format&fit=crop',
                'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=800&q=80&auto=format&fit=crop',
                'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800&q=80&auto=format&fit=crop',
            ];
        @endphp
        <div class="space-y-5">
            @foreach($roomTypes as $i => $rt)
                @php
                    $img = (! empty($rt->photos) && is_array($rt->photos) && count($rt->photos))
                        ? asset('storage/' . $rt->photos[0])
                        : $stockImages[$i % count($stockImages)];
                    $sub = $rt->base_rate * $roomsCount * $nights;
                    $taxPct = $rt->base_rate > 7500 ? 18 : 12;
                    $tax = round($sub * $taxPct / 100, 2);
                    $total = $sub + $tax;
                @endphp
                <div class="grid md:grid-cols-3 bg-cream-soft border border-line">
                    <div class="aspect-video md:aspect-auto bg-cover bg-center" style="background-image: url('{{ $img }}')"></div>
                    <div class="md:col-span-2 p-7 grid md:grid-cols-3 gap-6">
                        <div class="md:col-span-2">
                            <div class="text-xs uppercase tracking-widest text-brass-deep font-semibold mb-1">{{ $rt->code }}</div>
                            <h3 class="font-serif text-3xl mb-3">{{ $rt->name }}</h3>
                            <p class="text-sm text-ink-soft leading-relaxed mb-4">{{ \Illuminate\Support\Str::limit($rt->description ?: 'A handcrafted room with thoughtful details.', 180) }}</p>
                            <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-soft">
                                <span>✦ Sleeps {{ $rt->base_occupancy }}</span>
                                <span>✦ {{ ucfirst($rt->bed_type ?? 'King') }} bed</span>
                                <span>✦ Max {{ $rt->max_occupancy }} guests</span>
                            </div>
                        </div>
                        <div class="text-right border-t md:border-t-0 md:border-l border-line md:pl-6 pt-4 md:pt-0">
                            <div class="text-xs uppercase tracking-widest text-ink-mute mb-1">{{ $nights }} night{{ $nights > 1 ? 's' : '' }} × {{ $roomsCount }}</div>
                            <div class="font-serif text-3xl text-ink-soft">₹{{ number_format($sub, 0) }}</div>
                            <div class="text-xs text-ink-mute mt-1">+ ₹{{ number_format($tax, 0) }} GST</div>
                            <div class="font-serif text-2xl text-brass-deep mt-3 pt-3 border-t border-line">₹{{ number_format($total, 0) }} <span class="text-sm text-ink-mute italic">total</span></div>
                            <button wire:click="pickRoom({{ $rt->id }})" class="mt-4 w-full bg-ink hover:bg-brass-deep text-cream text-xs uppercase tracking-widest font-semibold py-3 transition">
                                Select this room
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>
@endif

@if($step === 'details')
<section class="max-w-3xl mx-auto px-6 py-12">
    <button wire:click="backToRooms" class="text-xs uppercase tracking-widest text-ink-mute hover:text-brass-deep mb-6">← Change room</button>
    @php
        $rt = $this->selectedRoomType;
        $sub = $rt ? $rt->base_rate * $roomsCount * $nights : 0;
        $taxPct = $rt && $rt->base_rate > 7500 ? 18 : 12;
        $tax = round($sub * $taxPct / 100, 2);
        $total = $sub + $tax;
    @endphp
    @if($rt)
        <div class="bg-cream-soft border border-line p-5 mb-6 flex items-center justify-between">
            <div>
                <div class="text-xs uppercase tracking-widest text-brass-deep font-semibold">{{ $rt->code }}</div>
                <div class="font-serif text-2xl">{{ $rt->name }}</div>
            </div>
            <div class="text-right">
                <div class="font-serif text-2xl text-brass-deep">₹{{ number_format($total, 0) }}</div>
                <div class="text-xs text-ink-mute">total · {{ $nights }} night{{ $nights > 1 ? 's' : '' }}</div>
            </div>
        </div>
    @endif

    <form wire:submit="submit" class="bg-cream border border-line p-8 space-y-5">
        <h2 class="font-serif text-3xl mb-2">Your details</h2>
        <p class="text-sm text-ink-soft mb-4">No payment now. We'll confirm your reservation by email and contact you for any deposit.</p>
        <div>
            <label class="block text-xs uppercase tracking-widest text-ink-mute font-semibold mb-1.5">Full name *</label>
            <input wire:model="guestName" class="w-full px-4 py-3 border border-line bg-white focus:outline-none focus:border-brass">
            @error('guestName')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
        </div>
        <div class="grid md:grid-cols-2 gap-5">
            <div>
                <label class="block text-xs uppercase tracking-widest text-ink-mute font-semibold mb-1.5">Email *</label>
                <input wire:model="guestEmail" type="email" class="w-full px-4 py-3 border border-line bg-white focus:outline-none focus:border-brass">
                @error('guestEmail')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="block text-xs uppercase tracking-widest text-ink-mute font-semibold mb-1.5">Phone *</label>
                <input wire:model="guestPhone" type="tel" class="w-full px-4 py-3 border border-line bg-white focus:outline-none focus:border-brass">
                @error('guestPhone')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
            </div>
        </div>
        <div>
            <label class="block text-xs uppercase tracking-widest text-ink-mute font-semibold mb-1.5">Special requests</label>
            <textarea wire:model="specialRequests" rows="3" class="w-full px-4 py-3 border border-line bg-white focus:outline-none focus:border-brass" placeholder="Late check-in, anniversary, dietary needs…"></textarea>
        </div>
        <button type="submit" class="w-full bg-ink hover:bg-brass-deep text-cream text-xs uppercase tracking-widest font-semibold py-4 transition" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="submit">Confirm reservation</span>
            <span wire:loading wire:target="submit">Reserving…</span>
        </button>
        <p class="text-[10px] text-ink-mute text-center pt-2">By confirming, you agree to {{ $tenant->name }}'s terms & cancellation policy.</p>
    </form>
</section>
@endif

@if($step === 'confirm')
<section class="max-w-2xl mx-auto px-6 py-20 text-center">
    <div class="text-brass text-6xl mb-6">✦</div>
    <h2 class="font-serif text-5xl mb-4">Reservation received</h2>
    <p class="text-ink-soft text-lg mb-8 leading-relaxed">
        Thank you, {{ explode(' ', $guestName ?: 'guest')[0] ?? 'guest' }}.<br>
        Your booking <strong class="font-mono">{{ $confirmationNumber }}</strong> is confirmed and waiting for you.
    </p>
    <div class="inline-block text-left bg-cream-soft border border-line p-6 mb-8">
        <div class="text-xs uppercase tracking-widest text-brass-deep font-semibold mb-2">Confirmation</div>
        <div class="font-mono text-2xl font-bold">{{ $confirmationNumber }}</div>
        <div class="text-xs text-ink-mute mt-2">A confirmation email is on its way.</div>
    </div>
    <p class="text-ink-soft mb-2">Need to reach us? Call {{ $property?->phone ?? '' }} or email {{ $property?->email ?? '' }}.</p>
    <a href="{{ Illuminate\Support\Facades\Route::has('hotel.home.dev') && ! str_contains(request()->getHost(), '.') ? route('hotel.home.dev', ['tenant_slug'=>$tenant_slug]) : route('hotel.home', ['tenant_slug'=>$tenant_slug]) }}"
       class="inline-flex items-center gap-2 mt-6 text-xs uppercase tracking-widest text-brass-deep border-b border-brass pb-1">← Back to home</a>
</section>
@endif
</div>
