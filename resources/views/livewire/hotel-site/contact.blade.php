@php
    $site = (array) ($property?->site_content ?? []);
    $cIntro    = $site['contact']['intro']    ?? "Whether you're celebrating a milestone, planning a quiet escape or simply have a question, our concierge replies within minutes.";
    $cAddress  = $site['contact']['address']  ?? ($property?->address ?: '');
    $cPhone    = $site['contact']['phone']    ?? ($property?->phone ?: '');
    $cEmail    = $site['contact']['email']    ?? ($property?->email ?: '');
    $cMapEmbed = $site['contact']['map_embed']?? '';
@endphp

<div>
<section class="pt-32 pb-12 text-center bg-cream">
    <div class="text-xs uppercase tracking-widest text-brass-deep font-semibold mb-4 flex items-center justify-center gap-3">
        <span class="gold-divider"></span> Reach us <span class="gold-divider"></span>
    </div>
    <h1 class="font-serif text-6xl md:text-7xl leading-[1.05]">Contact</h1>
</section>

<section class="max-w-6xl mx-auto px-6 py-20 grid md:grid-cols-2 gap-16">
    <div>
        <h2 class="font-serif text-4xl mb-6">Plan your stay</h2>
        <p class="text-ink-soft leading-relaxed mb-8 whitespace-pre-line">{{ $cIntro }}</p>
        <div class="space-y-5 text-base">
            @if($cAddress)
                <div>
                    <div class="text-xs uppercase tracking-widest text-brass-deep font-semibold mb-1">Address</div>
                    <div class="font-serif text-xl whitespace-pre-line">{{ $cAddress }}</div>
                    @if($property?->city)
                        <div class="text-ink-soft">{{ $property->city }}{{ $property->state ? ', '.$property->state : '' }}{{ $property->postal_code ? ' · '.$property->postal_code : '' }}</div>
                    @endif
                </div>
            @endif
            @if($cPhone)
                <div>
                    <div class="text-xs uppercase tracking-widest text-brass-deep font-semibold mb-1">Phone</div>
                    <a href="tel:{{ $cPhone }}" class="font-serif text-xl hover:text-brass-deep">{{ $cPhone }}</a>
                </div>
            @endif
            @if($cEmail)
                <div>
                    <div class="text-xs uppercase tracking-widest text-brass-deep font-semibold mb-1">Email</div>
                    <a href="mailto:{{ $cEmail }}" class="font-serif text-xl hover:text-brass-deep">{{ $cEmail }}</a>
                </div>
            @endif
            @if($cMapEmbed)
                <div class="pt-4">
                    <div class="text-xs uppercase tracking-widest text-brass-deep font-semibold mb-2">Find us</div>
                    <div class="aspect-[4/3] border border-line">
                        <iframe src="{{ $cMapEmbed }}" class="w-full h-full" style="border:0" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div>
        @if($submitted)
            <div class="bg-cream border-2 border-brass p-10 text-center">
                <div class="text-brass text-5xl mb-4">✦</div>
                <h3 class="font-serif text-3xl mb-3">Thank you</h3>
                <p class="text-ink-soft">Your message has reached us. A member of our team will be in touch shortly.</p>
                <button wire:click="$set('submitted', false)" class="mt-6 text-xs uppercase tracking-widest text-brass-deep border-b border-brass pb-1">Send another message</button>
            </div>
        @else
            <form wire:submit="submit" class="bg-cream border border-line p-8 space-y-5">
                <h3 class="font-serif text-2xl mb-2">Send us a note</h3>
                <div>
                    <label class="block text-xs uppercase tracking-widest text-ink-mute font-semibold mb-1.5">Your name *</label>
                    <input wire:model="name" type="text" class="w-full px-4 py-3 border border-line bg-white focus:outline-none focus:border-brass">
                    @error('name')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-widest text-ink-mute font-semibold mb-1.5">Email *</label>
                    <input wire:model="email" type="email" class="w-full px-4 py-3 border border-line bg-white focus:outline-none focus:border-brass">
                    @error('email')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-widest text-ink-mute font-semibold mb-1.5">Phone *</label>
                    <input wire:model="phone" type="tel" class="w-full px-4 py-3 border border-line bg-white focus:outline-none focus:border-brass">
                    @error('phone')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-widest text-ink-mute font-semibold mb-1.5">Message</label>
                    <textarea wire:model="message" rows="4" class="w-full px-4 py-3 border border-line bg-white focus:outline-none focus:border-brass"></textarea>
                </div>
                <button type="submit" class="w-full bg-ink hover:bg-brass-deep text-cream text-xs uppercase tracking-widest font-semibold py-4 transition" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="submit">Send message</span>
                    <span wire:loading wire:target="submit">Sending…</span>
                </button>
            </form>
        @endif
    </div>
</section>
</div>
