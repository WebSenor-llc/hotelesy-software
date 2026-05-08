<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? ($tenant->name ?? 'Hotel') }}</title>
    <meta name="description" content="{{ $description ?? ($tenant->name ?? 'Hotel') . ' — book your stay directly. No commission, best rates guaranteed.' }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Inter:wght@300;400;500;600;700&display=swap">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: {
              sans: ['Inter','ui-sans-serif','system-ui'],
              serif: ['"Cormorant Garamond"','Georgia','serif']
            },
            colors: {
              ink:    { DEFAULT:'#0d0d0c', soft:'#34322e', mute:'#827d72' },
              cream:  { DEFAULT:'#f5f0e6', soft:'#fbf8f1', dark:'#e8e0cf' },
              brass:  { DEFAULT:'#b8975a', deep:'#896d3c', soft:'#e8d9b6', glow:'#dbb777' },
              line:   '#dcd5c2',
            },
            letterSpacing: {
              widest: '.32em',
            }
          }
        }
      }
    </script>
    <style>
      body { font-family: 'Inter', sans-serif; background: #fbf8f1; color: #0d0d0c; }
      .font-serif { font-family: 'Cormorant Garamond', Georgia, serif; }
      .hero-vignette { background: radial-gradient(ellipse at center, transparent 30%, rgba(0,0,0,0.55) 100%); }
      .gold-divider { display:inline-block; width:60px; height:1px; background:#b8975a; vertical-align:middle; }
      .gold-text { color:#b8975a; }
      .h-pattern { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='60' height='60' viewBox='0 0 60 60'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23b8975a' fill-opacity='0.04'%3E%3Cpath d='M30 30c0-11 9-20 20-20v40c-11 0-20-9-20-20zm-20 0c0-11 9-20 20-20v40c-11 0-20-9-20-20z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E"); }
      [x-cloak]{display:none}
    </style>
    @livewireStyles
</head>
<body class="bg-cream-soft text-ink antialiased min-h-screen">

{{-- ===== TOP NAV ===== --}}
@php
    $isDev = ! str_contains((string) request()->getHost(), '.');
    $r = function (string $name, array $params = []) use ($tenant, $isDev) {
        $params = array_merge(['tenant_slug' => $tenant->slug], $params);
        $devName = $name . '.dev';
        if ($isDev && \Illuminate\Support\Facades\Route::has($devName)) {
            return route($devName, $params);
        }
        return route($name, $params);
    };
@endphp

<header x-data="{ open: false, scrolled: false }"
        @scroll.window="scrolled = window.scrollY > 50"
        :class="scrolled ? 'bg-cream-soft/95 backdrop-blur-md border-b border-line shadow-sm' : 'bg-transparent'"
        class="fixed top-0 inset-x-0 z-40 transition-all">
    <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
        <a href="{{ $r('hotel.home') }}" class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-ink text-brass-glow flex items-center justify-center font-serif font-semibold text-lg italic">
                {{ strtoupper(substr($tenant->name, 0, 1)) }}
            </div>
            <div>
                <div class="font-serif font-semibold text-xl text-ink leading-none">{{ $tenant->name }}</div>
                <div class="text-[10px] text-ink-mute uppercase tracking-widest mt-0.5">A boutique escape</div>
            </div>
        </a>

        {{-- Desktop nav --}}
        <nav class="hidden md:flex items-center gap-9 text-[13px] uppercase tracking-widest font-medium text-ink-soft">
            <a href="{{ $r('hotel.home') }}" class="hover:text-brass-deep transition">Home</a>
            <a href="{{ $r('hotel.rooms') }}" class="hover:text-brass-deep transition">Rooms</a>
            <a href="{{ $r('hotel.gallery') }}" class="hover:text-brass-deep transition">Gallery</a>
            <a href="{{ $r('hotel.about') }}" class="hover:text-brass-deep transition">About</a>
            <a href="{{ $r('hotel.contact') }}" class="hover:text-brass-deep transition">Contact</a>
        </nav>

        <div class="hidden md:flex items-center gap-3">
            <a href="{{ $r('hotel.book') }}" class="inline-flex items-center gap-2 bg-ink hover:bg-brass-deep text-cream text-xs uppercase tracking-widest font-semibold px-5 py-3 transition">
                Book direct
            </a>
        </div>

        {{-- Mobile burger --}}
        <button @click="open = !open" class="md:hidden text-ink">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M3 12h18M3 18h18" />
            </svg>
        </button>
    </div>
    {{-- Mobile menu --}}
    <div x-show="open" x-cloak class="md:hidden bg-cream-soft border-t border-line">
        <div class="max-w-7xl mx-auto px-6 py-4 flex flex-col gap-3 text-sm uppercase tracking-widest text-ink-soft">
            <a href="{{ $r('hotel.home') }}" class="py-1.5">Home</a>
            <a href="{{ $r('hotel.rooms') }}" class="py-1.5">Rooms</a>
            <a href="{{ $r('hotel.gallery') }}" class="py-1.5">Gallery</a>
            <a href="{{ $r('hotel.about') }}" class="py-1.5">About</a>
            <a href="{{ $r('hotel.contact') }}" class="py-1.5">Contact</a>
            <a href="{{ $r('hotel.book') }}" class="mt-2 inline-flex items-center gap-2 bg-ink text-cream text-xs uppercase tracking-widest font-semibold px-5 py-3">Book direct</a>
        </div>
    </div>
</header>

<main>{{ $slot }}</main>

{{-- ===== FOOTER ===== --}}
<footer class="bg-ink text-cream/70 mt-32 relative overflow-hidden">
    <div class="absolute inset-0 h-pattern pointer-events-none opacity-30"></div>
    <div class="relative max-w-7xl mx-auto px-6 py-20">
        <div class="grid md:grid-cols-4 gap-10">
            <div class="md:col-span-2">
                <div class="font-serif italic text-brass-glow text-sm tracking-widest uppercase mb-3">A boutique escape</div>
                <h3 class="font-serif text-4xl text-cream leading-tight">{{ $tenant->name }}</h3>
                @if($property)
                    <p class="text-sm text-cream/60 mt-4 max-w-md">
                        {{ $property->address }}{{ $property->city ? ', '.$property->city : '' }}
                        {{ $property->state ? ', '.$property->state : '' }} · {{ $property->country ?: 'India' }}
                    </p>
                    <div class="mt-6 space-y-1.5 text-sm">
                        @if($property->phone)<div>📞 <a href="tel:{{ $property->phone }}" class="hover:text-brass-glow">{{ $property->phone }}</a></div>@endif
                        @if($property->email)<div>✉️ <a href="mailto:{{ $property->email }}" class="hover:text-brass-glow">{{ $property->email }}</a></div>@endif
                    </div>
                @endif
            </div>
            <div>
                <div class="text-xs uppercase tracking-widest text-brass-glow mb-4 font-semibold">Discover</div>
                <ul class="space-y-2.5 text-sm">
                    <li><a href="{{ $r('hotel.rooms') }}" class="hover:text-cream">Rooms & suites</a></li>
                    <li><a href="{{ $r('hotel.gallery') }}" class="hover:text-cream">Gallery</a></li>
                    <li><a href="{{ $r('hotel.about') }}" class="hover:text-cream">Our story</a></li>
                    <li><a href="{{ $r('hotel.contact') }}" class="hover:text-cream">Contact us</a></li>
                </ul>
            </div>
            <div>
                <div class="text-xs uppercase tracking-widest text-brass-glow mb-4 font-semibold">Reserve</div>
                <ul class="space-y-2.5 text-sm">
                    <li><a href="{{ $r('hotel.book') }}" class="hover:text-cream">Book direct</a></li>
                    <li><a href="https://hotelesy.com" target="_blank" class="hover:text-cream">Powered by Hotelesy</a></li>
                </ul>
                <a href="{{ $r('hotel.book') }}" class="inline-flex items-center gap-2 mt-5 bg-brass hover:bg-brass-deep text-ink text-xs uppercase tracking-widest font-semibold px-5 py-3 transition">
                    Book your stay
                </a>
            </div>
        </div>
        <div class="border-t border-cream/10 mt-14 pt-6 flex flex-col md:flex-row items-center justify-between gap-3 text-xs text-cream/50">
            <div>© {{ date('Y') }} {{ $tenant->name }}. All rights reserved.</div>
            <div>Powered by <a href="https://hotelesy.com" class="hover:text-brass-glow underline">Hotelesy by WebSenor</a></div>
        </div>
    </div>
</footer>

@livewireScripts
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
