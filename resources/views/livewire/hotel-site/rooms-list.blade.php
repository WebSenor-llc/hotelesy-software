<div>
<section class="pt-32 pb-12 text-center bg-cream">
    <div class="text-xs uppercase tracking-widest text-brass-deep font-semibold mb-4 flex items-center justify-center gap-3">
        <span class="gold-divider"></span> Accommodation <span class="gold-divider"></span>
    </div>
    <h1 class="font-serif text-6xl md:text-7xl leading-[1.05]">Rooms & <em class="gold-text">suites</em></h1>
    <p class="text-ink-soft mt-5 max-w-xl mx-auto">Hand-finished spaces built for restorative stays.</p>
</section>

<section class="max-w-7xl mx-auto px-6 py-20">
    @if($roomTypes->isEmpty())
        <div class="text-center text-ink-mute py-20">No rooms configured yet.</div>
    @else
        @php
            $stockImages = [
                'https://images.unsplash.com/photo-1566665797739-1674de7a421a?w=1200&q=80&auto=format&fit=crop',
                'https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=1200&q=80&auto=format&fit=crop',
                'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=1200&q=80&auto=format&fit=crop',
                'https://images.unsplash.com/photo-1590490360182-c33d57733427?w=1200&q=80&auto=format&fit=crop',
                'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=1200&q=80&auto=format&fit=crop',
                'https://images.unsplash.com/photo-1631049552057-403cdb8f0658?w=1200&q=80&auto=format&fit=crop',
            ];
            $isDev = ! str_contains((string) request()->getHost(), '.');
        @endphp
        <div class="space-y-12">
            @foreach($roomTypes as $i => $rt)
                @php
                    $img = (! empty($rt->photos) && is_array($rt->photos) && count($rt->photos))
                        ? asset('storage/' . $rt->photos[0])
                        : $stockImages[$i % count($stockImages)];
                    $detailRoute = $isDev ? 'hotel.room.show.dev' : 'hotel.room.show';
                    $bookRoute = $isDev ? 'hotel.book.dev' : 'hotel.book';
                @endphp
                <div class="grid md:grid-cols-5 gap-0 bg-cream-soft border border-line {{ $i % 2 ? 'md:[direction:rtl]' : '' }}">
                    <div class="md:col-span-3 md:[direction:ltr]">
                        <div class="aspect-[16/10] bg-cover bg-center" style="background-image: url('{{ $img }}')"></div>
                    </div>
                    <div class="md:col-span-2 md:[direction:ltr] p-10 flex flex-col justify-center">
                        <div class="text-xs uppercase tracking-widest text-brass-deep font-semibold mb-3">{{ $rt->code }}</div>
                        <h2 class="font-serif text-4xl mb-4">{{ $rt->name }}</h2>
                        <p class="text-ink-soft leading-relaxed mb-6">{{ $rt->description ?: 'A handcrafted room with thoughtful details.' }}</p>
                        <ul class="space-y-2 mb-7 text-sm">
                            <li class="flex items-center gap-3"><span class="text-brass">✦</span> {{ $rt->base_occupancy }} guests · {{ $rt->max_occupancy }} max</li>
                            <li class="flex items-center gap-3"><span class="text-brass">✦</span> {{ ucfirst($rt->bed_type ?? 'King') }} bed</li>
                            <li class="flex items-center gap-3"><span class="text-brass">✦</span> Up to {{ $rt->extra_bed_capacity }} extra bed{{ $rt->extra_bed_capacity > 1 ? 's' : '' }}</li>
                        </ul>
                        <div class="flex items-baseline justify-between mb-6 pb-6 border-b border-line">
                            <div class="text-xs uppercase tracking-widest text-ink-mute">From</div>
                            <div class="font-serif text-3xl text-brass-deep">₹{{ number_format($rt->base_rate, 0) }}<span class="text-sm text-ink-mute italic"> / night</span></div>
                        </div>
                        <div class="flex gap-3">
                            <a href="{{ route($detailRoute, ['tenant_slug'=>$tenant_slug, 'roomTypeCode'=>$rt->code]) }}" class="flex-1 text-center border border-ink hover:bg-ink hover:text-cream text-ink text-xs uppercase tracking-widest font-semibold py-3 transition">View details</a>
                            <a href="{{ route($bookRoute, ['tenant_slug'=>$tenant_slug, 'room_type'=>$rt->code]) }}" class="flex-1 text-center bg-ink hover:bg-brass-deep text-cream text-xs uppercase tracking-widest font-semibold py-3 transition">Book this room</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>
</div>
