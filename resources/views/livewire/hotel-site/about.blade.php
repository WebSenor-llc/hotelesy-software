@php
    $site = (array) ($property?->site_content ?? []);
    $aboutTitle = $site['about']['title'] ?? 'About us';
    $aboutBody  = trim($site['about']['body'] ?? '');
    $aboutImage = $site['about']['image'] ?? null;
    $aboutBg    = $aboutImage
        ? asset('storage/' . ltrim($aboutImage, '/'))
        : 'https://images.unsplash.com/photo-1564501049412-61c2a3083791?w=1600&q=80&auto=format&fit=crop';
@endphp

<div>
<section class="pt-32 pb-12 text-center bg-cream">
    <div class="text-xs uppercase tracking-widest text-brass-deep font-semibold mb-4 flex items-center justify-center gap-3">
        <span class="gold-divider"></span> Our story <span class="gold-divider"></span>
    </div>
    <h1 class="font-serif text-6xl md:text-7xl leading-[1.05]">{{ $aboutTitle }}</h1>
</section>

<section class="max-w-4xl mx-auto px-6 py-20">
    <div class="aspect-[16/9] bg-cover bg-center mb-12" style="background-image: url('{{ $aboutBg }}')"></div>
    <div class="prose prose-lg max-w-none font-light text-ink-soft leading-relaxed">
        @if($aboutBody !== '')
            {{-- CMS-supplied body. Blank-line-separated blocks render as paragraphs;
                 the first one gets the lead-paragraph styling. --}}
            @foreach(preg_split("/\n\s*\n/", $aboutBody) as $i => $paragraph)
                <p @if($i === 0) class="text-2xl font-serif italic text-ink mb-6" @endif>{{ trim($paragraph) }}</p>
            @endforeach
        @else
            <p class="text-2xl font-serif italic text-ink mb-6">{{ $tenant->name }} is a small hotel, but the kind that takes hospitality very seriously.</p>
            <p>Founded with a single belief — that travellers deserve more than just a room. They deserve a feeling. The kind of feeling you carry quietly home with you, long after the bags are unpacked.</p>
            <p>Every room, every corridor, every detail of {{ $tenant->name }} has been touched by hand. Local artisans crafted the brassware. Family recipes inform the menu.</p>
            @if($property)
                <p>Located in the heart of {{ $property->city }}, {{ $property->state ?: 'India' }}, we sit close enough to the city's most-loved sights — yet far enough that you'll forget they exist for a while.</p>
            @endif
            <p>Thank you for considering us. We hope to welcome you soon.</p>
        @endif
    </div>
</section>
</div>
