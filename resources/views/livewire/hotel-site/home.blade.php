@php
    // Hotel-site CMS payload (lives on $property->site_content). Each field
    // falls back to a sensible default so the page works whether or not the
    // hotelier has filled in the CMS.
    $site = (array) ($property?->site_content ?? []);
    $heroHeadline    = $site['hero']['headline']    ?? $tenant->name;
    $heroSubheadline = $site['hero']['subheadline'] ?? ('A boutique escape in ' . ($property?->city ?: 'India') . '.');
    $heroCtaLabel    = $site['hero']['cta_label']   ?? 'Reserve your stay';
    $heroSlides      = (array) ($site['hero']['slides'] ?? []);
    // Use the first uploaded slide as the hero background; otherwise fall back
    // to the historical Unsplash placeholder so the hero never goes black.
    $heroBg = !empty($heroSlides)
        ? asset('storage/' . ltrim($heroSlides[0], '/'))
        : 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=1800&q=80&auto=format&fit=crop';
@endphp

<div>
{{-- ===================== HERO ===================== --}}
<section class="relative min-h-screen flex items-center justify-center overflow-hidden">
    <div class="absolute inset-0 bg-ink"
         style="background-image: url('{{ $heroBg }}'); background-size: cover; background-position: center;">
    </div>
    <div class="absolute inset-0 hero-vignette"></div>
    <div class="absolute inset-0 bg-ink/30"></div>

    <div class="relative z-10 text-center text-cream max-w-4xl px-6 py-32">
        <div class="mb-6 flex items-center justify-center gap-3 text-brass-glow">
            <span class="gold-divider"></span>
            <span class="text-xs uppercase tracking-widest font-medium">Welcome to</span>
            <span class="gold-divider"></span>
        </div>
        <h1 class="font-serif text-6xl md:text-8xl leading-[0.95] mb-6 italic">
            {{ $heroHeadline }}
        </h1>
        <p class="text-base md:text-lg text-cream/85 max-w-2xl mx-auto leading-relaxed font-light">
            {{ $heroSubheadline }}
        </p>
        <div class="mt-12 flex items-center justify-center gap-4">
            <a href="#booking" class="bg-brass hover:bg-brass-deep text-ink text-xs uppercase tracking-widest font-semibold px-8 py-4 transition">
                {{ $heroCtaLabel }}
            </a>
            <a href="#rooms" class="border border-cream/40 hover:border-cream text-cream text-xs uppercase tracking-widest font-semibold px-8 py-4 transition">
                Discover rooms
            </a>
        </div>
    </div>

    {{-- Scroll cue --}}
    <div class="absolute bottom-8 left-1/2 -translate-x-1/2 text-cream/60 text-[10px] uppercase tracking-widest flex flex-col items-center gap-2 animate-bounce">
        <span>Scroll</span>
        <span>↓</span>
    </div>
</section>

{{-- ===================== BOOKING WIDGET ===================== --}}
<section id="booking" class="relative -mt-20 z-20 px-6">
    <div class="max-w-5xl mx-auto bg-cream-soft shadow-2xl border border-line">
        <div class="grid md:grid-cols-6 divide-y md:divide-y-0 md:divide-x divide-line">
            <div class="md:col-span-5 grid md:grid-cols-5 divide-y md:divide-y-0 md:divide-x divide-line">
                <div class="px-5 py-5">
                    <label class="block text-[10px] uppercase tracking-widest text-ink-mute font-semibold mb-1.5">Check in</label>
                    <input type="date" wire:model="checkIn" class="w-full text-base font-serif font-medium bg-transparent focus:outline-none">
                </div>
                <div class="px-5 py-5">
                    <label class="block text-[10px] uppercase tracking-widest text-ink-mute font-semibold mb-1.5">Check out</label>
                    <input type="date" wire:model="checkOut" class="w-full text-base font-serif font-medium bg-transparent focus:outline-none">
                </div>
                <div class="px-5 py-5">
                    <label class="block text-[10px] uppercase tracking-widest text-ink-mute font-semibold mb-1.5">Adults</label>
                    <select wire:model="adults" class="w-full text-base font-serif font-medium bg-transparent focus:outline-none">
                        @for($i = 1; $i <= 8; $i++)<option value="{{ $i }}">{{ $i }} adult{{ $i > 1 ? 's' : '' }}</option>@endfor
                    </select>
                </div>
                <div class="px-5 py-5">
                    <label class="block text-[10px] uppercase tracking-widest text-ink-mute font-semibold mb-1.5">Children</label>
                    <select wire:model="children" class="w-full text-base font-serif font-medium bg-transparent focus:outline-none">
                        @for($i = 0; $i <= 4; $i++)<option value="{{ $i }}">{{ $i }} {{ $i === 1 ? 'child' : 'children' }}</option>@endfor
                    </select>
                </div>
                <div class="px-5 py-5">
                    <label class="block text-[10px] uppercase tracking-widest text-ink-mute font-semibold mb-1.5">Rooms</label>
                    <select wire:model="rooms" class="w-full text-base font-serif font-medium bg-transparent focus:outline-none">
                        @for($i = 1; $i <= 5; $i++)<option value="{{ $i }}">{{ $i }} room{{ $i > 1 ? 's' : '' }}</option>@endfor
                    </select>
                </div>
            </div>
            <button wire:click="searchAvailability" class="bg-ink hover:bg-brass-deep text-cream text-xs uppercase tracking-widest font-semibold transition">
                Check availability
            </button>
        </div>
    </div>
    <p class="text-center text-xs text-ink-mute mt-4">Best rate guaranteed when you book direct · No commission, no booking fee</p>
</section>

{{-- ===================== INTRODUCTION ===================== --}}
<section class="max-w-7xl mx-auto px-6 py-32">
    <div class="grid md:grid-cols-2 gap-16 items-center">
        <div>
            <div class="text-xs uppercase tracking-widest text-brass-deep font-semibold mb-4 flex items-center gap-3">
                <span class="gold-divider"></span> Our story
            </div>
            <h2 class="font-serif text-5xl md:text-6xl leading-[1.05] mb-6">A sanctuary of <em class="font-medium gold-text">timeless</em> elegance.</h2>
            <p class="text-ink-soft leading-relaxed text-lg mb-5">
                Every detail at {{ $tenant->name }} is crafted to feel both intimate and unforgettable —
                from hand-finished interiors to curated experiences that reveal the soul of {{ $property?->city ?? 'this region' }}.
            </p>
            <p class="text-ink-soft leading-relaxed">
                Whether you're here for an unhurried weekend or a once-in-a-lifetime celebration,
                our team will quietly anticipate every wish — long before you've thought of it.
            </p>
            <a href="{{ Illuminate\Support\Facades\Route::has('hotel.about.dev') && ! str_contains(request()->getHost(), '.') ? route('hotel.about.dev', ['tenant_slug'=>$tenant_slug]) : route('hotel.about', ['tenant_slug'=>$tenant_slug]) }}"
               class="inline-flex items-center gap-2 mt-8 text-brass-deep hover:text-brass text-xs uppercase tracking-widest font-semibold border-b border-brass pb-1">
                Discover our story →
            </a>
        </div>
        <div class="relative aspect-[4/5] overflow-hidden">
            <div class="absolute inset-0 bg-cover bg-center"
                 style="background-image: url('https://images.unsplash.com/photo-1582719508461-905c673771fd?w=900&q=80&auto=format&fit=crop')"></div>
            <div class="absolute -bottom-4 -right-4 bg-brass text-ink p-6 max-w-[260px] shadow-xl">
                <div class="font-serif italic text-3xl mb-1">Est. {{ now()->subYears(8)->year }}</div>
                <div class="text-[10px] uppercase tracking-widest font-semibold">Crafting unforgettable stays</div>
            </div>
        </div>
    </div>
</section>

{{-- ===================== ROOMS ===================== --}}
<section id="rooms" class="bg-cream py-32 border-y border-line">
    <div class="max-w-7xl mx-auto px-6">
        <div class="text-center max-w-2xl mx-auto mb-16">
            <div class="text-xs uppercase tracking-widest text-brass-deep font-semibold mb-4 flex items-center justify-center gap-3">
                <span class="gold-divider"></span> Accommodation <span class="gold-divider"></span>
            </div>
            <h2 class="font-serif text-5xl md:text-6xl leading-[1.05]">Rooms & <em class="gold-text">suites</em></h2>
            <p class="text-ink-soft mt-5">Each room is a quiet retreat — uncluttered, considered, and finished with hand-picked details.</p>
        </div>

        @if($roomTypes->isEmpty())
            <div class="text-center text-ink-mute py-12">Rooms coming soon.</div>
        @else
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                @php
                    $stockImages = [
                        'https://images.unsplash.com/photo-1566665797739-1674de7a421a?w=800&q=80&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=800&q=80&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=800&q=80&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1590490360182-c33d57733427?w=800&q=80&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800&q=80&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1631049552057-403cdb8f0658?w=800&q=80&auto=format&fit=crop',
                    ];
                @endphp
                @foreach($roomTypes as $i => $rt)
                    @php
                        $img = (! empty($rt->photos) && is_array($rt->photos) && count($rt->photos))
                            ? asset('storage/' . $rt->photos[0])
                            : $stockImages[$i % count($stockImages)];
                        $isDev = ! str_contains((string) request()->getHost(), '.');
                        $detailRoute = $isDev ? 'hotel.room.show.dev' : 'hotel.room.show';
                    @endphp
                    <a href="{{ route($detailRoute, ['tenant_slug' => $tenant_slug, 'roomTypeCode' => $rt->code]) }}"
                       class="group block bg-cream-soft border border-line hover:shadow-2xl transition-shadow duration-500">
                        <div class="aspect-[4/3] overflow-hidden">
                            <div class="w-full h-full bg-cover bg-center transition duration-700 group-hover:scale-105"
                                 style="background-image: url('{{ $img }}')"></div>
                        </div>
                        <div class="p-6">
                            <div class="flex items-baseline justify-between mb-2">
                                <h3 class="font-serif text-2xl">{{ $rt->name }}</h3>
                                <div class="text-brass-deep font-serif italic">From ₹{{ number_format($rt->base_rate, 0) }}</div>
                            </div>
                            <p class="text-sm text-ink-soft line-clamp-2 mb-5 leading-relaxed">{{ $rt->description ?: 'A handcrafted room with thoughtful details, designed for restorative stays.' }}</p>
                            <div class="flex items-center justify-between">
                                <div class="text-xs text-ink-mute uppercase tracking-widest">
                                    {{ $rt->base_occupancy }} guest{{ $rt->base_occupancy > 1 ? 's' : '' }} · {{ ucfirst($rt->bed_type ?? 'King') }} bed
                                </div>
                                <span class="text-xs uppercase tracking-widest text-brass-deep font-semibold opacity-0 group-hover:opacity-100 transition">View →</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</section>

{{-- ===================== EXPERIENCES ===================== --}}
<section class="max-w-7xl mx-auto px-6 py-32">
    <div class="text-center max-w-2xl mx-auto mb-16">
        <div class="text-xs uppercase tracking-widest text-brass-deep font-semibold mb-4 flex items-center justify-center gap-3">
            <span class="gold-divider"></span> Experiences <span class="gold-divider"></span>
        </div>
        <h2 class="font-serif text-5xl md:text-6xl leading-[1.05]">Beyond the <em class="gold-text">stay</em></h2>
    </div>

    @php
        $experiences = [
            ['title'=>'Signature Spa', 'desc'=>'Hand-blended oils, ancestral techniques and a calm that settles deep.', 'img'=>'https://images.unsplash.com/photo-1540555700478-4be289fbecef?w=900&q=80&auto=format&fit=crop'],
            ['title'=>'Lakeside Dining', 'desc'=>'Seasonal menus from our chef, served on a candle-lit terrace by the water.', 'img'=>'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=900&q=80&auto=format&fit=crop'],
            ['title'=>'Heritage Walks', 'desc'=>'Guided stories through palace courtyards, hidden temples and quiet bazaars.', 'img'=>'https://images.unsplash.com/photo-1564507592333-c60657eea523?w=900&q=80&auto=format&fit=crop'],
        ];
    @endphp
    <div class="grid md:grid-cols-3 gap-6">
        @foreach($experiences as $exp)
            <div class="group">
                <div class="aspect-[3/4] overflow-hidden mb-5">
                    <div class="w-full h-full bg-cover bg-center transition duration-700 group-hover:scale-105" style="background-image: url('{{ $exp['img'] }}')"></div>
                </div>
                <h3 class="font-serif text-2xl mb-2">{{ $exp['title'] }}</h3>
                <p class="text-sm text-ink-soft leading-relaxed">{{ $exp['desc'] }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- ===================== TESTIMONIALS ===================== --}}
<section class="bg-ink text-cream py-32 relative overflow-hidden">
    <div class="absolute inset-0 h-pattern opacity-30 pointer-events-none"></div>
    <div class="relative max-w-4xl mx-auto px-6 text-center">
        <div class="text-xs uppercase tracking-widest text-brass-glow font-semibold mb-4 flex items-center justify-center gap-3">
            <span class="gold-divider"></span> Guest stories <span class="gold-divider"></span>
        </div>
        <blockquote class="font-serif italic text-3xl md:text-4xl leading-[1.3] text-cream max-w-3xl mx-auto">
            "Every detail felt deeply considered — from the first Namaste at the door to the kheer at midnight.
            The kind of place that quietly stays with you long after you've left."
        </blockquote>
        <div class="mt-10 flex items-center justify-center gap-1.5 text-brass-glow">★★★★★</div>
        <div class="mt-4 text-sm text-cream/70">
            <strong class="text-cream font-semibold">Aanya & Karan</strong> · Anniversary stay, March 2026
        </div>
    </div>
</section>

{{-- ===================== FINAL CTA ===================== --}}
<section class="max-w-7xl mx-auto px-6 py-32 text-center">
    <div class="text-xs uppercase tracking-widest text-brass-deep font-semibold mb-4 flex items-center justify-center gap-3">
        <span class="gold-divider"></span> Reserve your stay <span class="gold-divider"></span>
    </div>
    <h2 class="font-serif text-5xl md:text-7xl leading-[1.05] mb-6">An <em class="gold-text">unforgettable</em> stay awaits.</h2>
    <p class="text-ink-soft text-lg max-w-2xl mx-auto mb-10">Book direct for the lowest rates and complimentary perks reserved for our guests.</p>
    <a href="#booking" class="inline-flex items-center gap-2 bg-ink hover:bg-brass-deep text-cream text-xs uppercase tracking-widest font-semibold px-10 py-5 transition">
        Reserve now
    </a>
</section>
</div>
