<div>
<section class="pt-32 pb-12 text-center bg-cream">
    <div class="text-xs uppercase tracking-widest text-brass-deep font-semibold mb-4 flex items-center justify-center gap-3">
        <span class="gold-divider"></span> Visual journal <span class="gold-divider"></span>
    </div>
    <h1 class="font-serif text-6xl md:text-7xl leading-[1.05]">Gallery</h1>
    <p class="text-ink-soft mt-5 max-w-xl mx-auto">A glimpse into the spaces, flavours and quiet moments at {{ $tenant->name }}.</p>
</section>

<section class="max-w-7xl mx-auto px-6 py-20">
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
        @foreach([
            'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80&auto=format&fit=crop',
            'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=800&q=80&auto=format&fit=crop',
            'https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=800&q=80&auto=format&fit=crop',
            'https://images.unsplash.com/photo-1540555700478-4be289fbecef?w=800&q=80&auto=format&fit=crop',
            'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=800&q=80&auto=format&fit=crop',
            'https://images.unsplash.com/photo-1564507592333-c60657eea523?w=800&q=80&auto=format&fit=crop',
            'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=800&q=80&auto=format&fit=crop',
            'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800&q=80&auto=format&fit=crop',
            'https://images.unsplash.com/photo-1631049552057-403cdb8f0658?w=800&q=80&auto=format&fit=crop',
        ] as $i => $img)
            <div class="aspect-{{ $i % 4 === 0 ? 'square' : ($i % 4 === 2 ? '[3/4]' : 'square') }} bg-cover bg-center hover:scale-[1.02] transition duration-500 cursor-pointer"
                 style="background-image: url('{{ $img }}')"></div>
        @endforeach
    </div>
</section>
</div>
