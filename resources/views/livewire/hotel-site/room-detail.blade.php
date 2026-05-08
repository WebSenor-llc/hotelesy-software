<div>
@php
    $stockImages = [
        'https://images.unsplash.com/photo-1566665797739-1674de7a421a?w=1600&q=80&auto=format&fit=crop',
        'https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=1600&q=80&auto=format&fit=crop',
        'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=1600&q=80&auto=format&fit=crop',
        'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=1600&q=80&auto=format&fit=crop',
    ];
    $photos = (! empty($roomType->photos) && is_array($roomType->photos) && count($roomType->photos))
        ? collect($roomType->photos)->map(fn($p) => asset('storage/' . $p))->all()
        : $stockImages;
    $isDev = ! str_contains((string) request()->getHost(), '.');
    $bookRoute = $isDev ? 'hotel.book.dev' : 'hotel.book';
@endphp

<section class="pt-24">
    <div class="max-w-7xl mx-auto px-6 mb-8">
        <a href="{{ ($isDev ? route('hotel.rooms.dev', ['tenant_slug'=>$tenant_slug]) : route('hotel.rooms', ['tenant_slug'=>$tenant_slug])) }}" class="text-xs uppercase tracking-widest text-ink-mute hover:text-brass-deep">← All rooms</a>
    </div>

    <div class="max-w-7xl mx-auto px-6 grid lg:grid-cols-5 gap-12">
        {{-- Gallery --}}
        <div class="lg:col-span-3">
            <div class="aspect-[16/10] bg-cover bg-center mb-3" style="background-image: url('{{ $photos[0] }}')"></div>
            <div class="grid grid-cols-3 gap-3">
                @foreach(array_slice($photos, 1, 3) as $p)
                    <div class="aspect-square bg-cover bg-center" style="background-image: url('{{ $p }}')"></div>
                @endforeach
            </div>
        </div>

        {{-- Info --}}
        <div class="lg:col-span-2">
            <div class="text-xs uppercase tracking-widest text-brass-deep font-semibold mb-2">{{ $roomType->code }}</div>
            <h1 class="font-serif text-5xl leading-[1.05] mb-4">{{ $roomType->name }}</h1>
            <p class="text-ink-soft leading-relaxed text-lg mb-8">{{ $roomType->description ?: 'A handcrafted retreat designed for restorative stays.' }}</p>

            <div class="bg-cream-soft border border-line p-6 mb-6">
                <div class="flex items-baseline justify-between pb-4 border-b border-line">
                    <div class="text-xs uppercase tracking-widest text-ink-mute">Starts at</div>
                    <div class="font-serif text-4xl text-brass-deep">₹{{ number_format($roomType->base_rate, 0) }}<span class="text-base text-ink-mute italic"> / night</span></div>
                </div>
                <ul class="mt-5 space-y-2.5 text-sm">
                    <li class="flex items-center gap-3"><span class="text-brass">✦</span> Sleeps {{ $roomType->base_occupancy }} (max {{ $roomType->max_occupancy }})</li>
                    <li class="flex items-center gap-3"><span class="text-brass">✦</span> {{ ucfirst($roomType->bed_type ?? 'King') }} bed</li>
                    <li class="flex items-center gap-3"><span class="text-brass">✦</span> Up to {{ $roomType->extra_bed_capacity }} extra bed{{ $roomType->extra_bed_capacity > 1 ? 's' : '' }}</li>
                    <li class="flex items-center gap-3"><span class="text-brass">✦</span> {{ $roomType->max_adults }} adults · {{ $roomType->max_children }} children</li>
                </ul>
                <a href="{{ route($bookRoute, ['tenant_slug'=>$tenant_slug, 'room_type'=>$roomType->code]) }}" class="block text-center mt-6 bg-ink hover:bg-brass-deep text-cream text-xs uppercase tracking-widest font-semibold py-4 transition">
                    Reserve this room
                </a>
            </div>

            <div class="text-xs text-ink-mute">
                <strong>Direct booking benefits:</strong> Best rate guaranteed · No booking fee · Late checkout when available
            </div>
        </div>
    </div>
</section>

<section class="bg-cream py-24 mt-24">
    <div class="max-w-3xl mx-auto px-6 text-center">
        <h2 class="font-serif text-4xl md:text-5xl">Have a question? <em class="gold-text">We're listening.</em></h2>
        <p class="text-ink-soft mt-4 mb-7">Our concierge team replies within minutes — not days.</p>
        @if($property?->phone)<a href="tel:{{ $property->phone }}" class="font-serif text-3xl text-brass-deep">{{ $property->phone }}</a>@endif
    </div>
</section>
</div>
