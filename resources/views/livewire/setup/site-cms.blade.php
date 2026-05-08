<div>
    <div class="flex items-baseline justify-between mb-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Hotel website CMS</h1>
            <p class="text-sm text-slate-500">Edit the public marketing site at <code class="bg-slate-100 px-1 rounded text-[11px]">/h/{{ $tenantSlug ?? '...' }}</code>. Room cards are pulled from your active room types automatically.</p>
        </div>
        @if($tenantSlug)
            <a href="{{ url('/h/'.$tenantSlug) }}" target="_blank"
               class="bg-brand-50 hover:bg-brand-100 text-brand-700 font-semibold px-4 py-2 rounded-lg text-sm border border-brand-200">
                ↗ Open public site
            </a>
        @endif
    </div>

    @if(session('success'))<div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200">✓ {{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-3 rounded-lg bg-rose-50 text-rose-800 border border-rose-200">✗ {{ session('error') }}</div>@endif
    @if($errors->any())
        <div class="mb-4 px-4 py-3 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-sm">
            <div class="font-semibold mb-1">Please fix:</div>
            <ul class="list-disc list-inside text-xs">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form wire:submit.prevent="save" class="space-y-6">

        {{-- HERO --}}
        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 mb-4">Hero section</h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium mb-1">Headline *</label>
                    <input type="text" wire:model="hero_headline" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium mb-1">Sub-headline</label>
                    <input type="text" wire:model="hero_subheadline" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Booking button label</label>
                    <input type="text" wire:model="hero_cta_label" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-xs font-medium mb-1">Slider images</label>
                @if(!empty($existingSlides))
                    <div class="grid grid-cols-3 md:grid-cols-5 gap-2 mb-2">
                        @foreach($existingSlides as $slide)
                            <div class="relative group rounded overflow-hidden border border-slate-200">
                                <img src="{{ asset('storage/'.$slide) }}" class="w-full aspect-[4/3] object-cover">
                                <button type="button" wire:click="removeHeroSlide(@js($slide))"
                                        wire:confirm="Remove this slide?"
                                        class="absolute top-1 right-1 bg-rose-600 hover:bg-rose-700 text-white text-[10px] px-2 py-0.5 rounded opacity-0 group-hover:opacity-100 transition">×</button>
                            </div>
                        @endforeach
                    </div>
                @endif
                <input type="file" wire:model="heroSlideUploads" multiple accept="image/*"
                       class="w-full px-2 py-1.5 border rounded text-xs file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:bg-slate-100">
                <div wire:loading wire:target="heroSlideUploads" class="text-[10px] text-brand-600 mt-1">Uploading…</div>
                <div class="text-[10px] text-slate-500 mt-1">Recommended: 1920×1080 JPG/PNG/WebP, max 5 MB each. Multiple files allowed.</div>
            </div>
        </div>

        {{-- ABOUT --}}
        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 mb-4">About section</h2>
            <div class="grid md:grid-cols-3 gap-4">
                <div class="md:col-span-2 space-y-3">
                    <div>
                        <label class="block text-xs font-medium mb-1">Title</label>
                        <input type="text" wire:model="about_title" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1">Body (paragraphs)</label>
                        <textarea wire:model="about_body" rows="6" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="Tell guests about the hotel — history, location, what makes it special."></textarea>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">About image</label>
                    @if($about_image)
                        <div class="mb-2 relative inline-block">
                            <img src="{{ asset('storage/'.$about_image) }}" class="rounded border w-full aspect-[4/3] object-cover">
                            <button type="button" wire:click="removeAboutImage" wire:confirm="Remove this image?"
                                    class="absolute top-1 right-1 bg-rose-600 text-white text-[10px] px-2 py-0.5 rounded">×</button>
                        </div>
                    @endif
                    <input type="file" wire:model="aboutImageUpload" accept="image/*"
                           class="w-full px-2 py-1.5 border rounded text-xs file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:bg-slate-100">
                    <div wire:loading wire:target="aboutImageUpload" class="text-[10px] text-brand-600 mt-1">Uploading…</div>
                </div>
            </div>
        </div>

        {{-- AMENITIES --}}
        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 mb-4">Amenities</h2>
            <label class="block text-xs font-medium mb-1">One amenity per line</label>
            <textarea wire:model="amenities_text" rows="6" class="w-full px-3 py-2 border rounded-lg text-sm font-mono" placeholder="Free Wi-Fi&#10;Airport pickup&#10;Swimming pool"></textarea>
            <div class="text-[10px] text-slate-500 mt-1">Shown on the home page and amenity strip. Keep each line short.</div>
        </div>

        {{-- CONTACT --}}
        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 mb-4">Contact page</h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium mb-1">Intro text</label>
                    <textarea wire:model="contact_intro" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium mb-1">Address (multi-line)</label>
                    <textarea wire:model="contact_address" rows="3" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Phone</label>
                    <input type="tel" wire:model="contact_phone" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Email</label>
                    <input type="email" wire:model="contact_email" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium mb-1">Google Maps embed (paste &lt;iframe&gt; src URL)</label>
                    <input type="text" wire:model="contact_map_embed" class="w-full px-3 py-2 border rounded-lg text-sm font-mono" placeholder="https://www.google.com/maps/embed?pb=...">
                    <div class="text-[10px] text-slate-500 mt-1">From Google Maps → Share → Embed → copy only the URL inside <code>src=""</code>.</div>
                </div>
            </div>
        </div>

        {{-- FOOTER --}}
        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 mb-4">Footer</h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium mb-1">Tagline</label>
                    <input type="text" wire:model="footer_tagline" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Copyright line</label>
                    <input type="text" wire:model="footer_copyright" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
            </div>
        </div>

        <div class="flex justify-end pt-3 sticky bottom-4 z-10">
            <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-6 py-2.5 rounded-lg text-sm shadow-lg"
                    wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Save site content</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>
    </form>
</div>
