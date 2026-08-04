<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Hotelesy — Cloud Hotel Management Software for Modern Hoteliers' }}</title>
    <meta name="description" content="{{ $description ?? 'Hotelesy unifies your PMS, channel manager, booking engine, POS and revenue intelligence in one beautiful cloud platform — so your team runs the hotel, not the software.' }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: {
              sans: ['Inter','ui-sans-serif','system-ui'],
              serif: ['"Instrument Serif"','Georgia','serif']
            },
            colors: {
              brand:  { DEFAULT:'hsl(220 90% 56%)', deep:'hsl(222 84% 45%)', soft:'hsl(214 100% 97%)', fg:'hsl(0 0% 100%)' },
              ink:    {
                DEFAULT:'hsl(222 47% 11%)', soft:'hsl(215 16% 35%)', mute:'hsl(215 16% 50%)',
                50:'hsl(220 30% 98%)',  100:'hsl(220 20% 94%)', 200:'hsl(220 16% 88%)',
                300:'hsl(220 14% 78%)', 400:'hsl(215 16% 60%)', 500:'hsl(215 16% 50%)',
                600:'hsl(215 16% 40%)', 700:'hsl(215 18% 30%)', 800:'hsl(220 30% 18%)',
                900:'hsl(222 47% 11%)'
              },
              gold:   { 100:'hsl(45 90% 92%)', 300:'hsl(43 88% 72%)', 600:'hsl(38 85% 42%)', 700:'hsl(35 85% 35%)' },
              subtle: 'hsl(30 30% 97%)',
              line:   'hsl(220 14% 92%)',
              success:'hsl(142 71% 41%)'
            },
            backgroundImage: {
              'gradient-hero': 'radial-gradient(1200px 600px at 80% -10%, hsl(214 100% 95%) 0%, transparent 60%), linear-gradient(180deg, hsl(220 30% 99%) 0%, hsl(220 25% 97%) 100%)',
              'gradient-brand':'linear-gradient(135deg, hsl(220 90% 60%) 0%, hsl(232 84% 50%) 100%)',
              'gradient-dark': 'linear-gradient(135deg, hsl(222 47% 11%) 0%, hsl(222 47% 6%) 100%)',
            },
            boxShadow: {
              'pop':     '0 10px 30px -10px hsl(220 90% 56% / 0.45)',
              'product': '0 30px 80px -20px hsl(222 47% 11% / 0.18), 0 8px 20px -6px hsl(222 47% 11% / 0.08)',
              'card':    '0 4px 14px -4px hsl(222 47% 11% / 0.10)',
            },
            keyframes: {
              float: { '0%,100%':{transform:'translateY(0)'}, '50%':{transform:'translateY(-8px)'} },
              marquee: { '0%':{transform:'translateX(0)'}, '100%':{transform:'translateX(-50%)'} },
              fadeup: { '0%':{opacity:'0',transform:'translateY(12px)'}, '100%':{opacity:'1',transform:'translateY(0)'} },
            },
            animation: {
              float:   'float 6s ease-in-out infinite',
              marquee: 'marquee 35s linear infinite',
              fadeup:  'fadeup .8s ease-out both',
            }
          }
        }
      }
    </script>
    <style>
      body { font-family: 'Inter', sans-serif; -webkit-font-smoothing: antialiased; }
      .font-serif { font-family: 'Instrument Serif', Georgia, serif; }
      .grid-bg { background-image: linear-gradient(hsl(220 14% 92%) 1px, transparent 1px), linear-gradient(90deg, hsl(220 14% 92%) 1px, transparent 1px); background-size: 56px 56px; mask-image: radial-gradient(ellipse at center, black 30%, transparent 70%); }
      .dot-bg { background-image: radial-gradient(rgba(255,255,255,0.15) 1px, transparent 1px); background-size: 16px 16px; }
      [x-cloak]{display:none}
      .marquee-track { display:flex; width:max-content; animation: marquee 35s linear infinite; }
      details > summary::-webkit-details-marker { display:none; }
    </style>
    @livewireStyles
</head>
<body class="bg-white text-ink antialiased min-h-screen">
{{-- ===== TOP NAV ===== --}}
<header class="sticky top-0 z-50 bg-white/80 backdrop-blur border-b border-line">
    <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-brand text-white font-bold flex items-center justify-center text-base">h</div>
            <span class="font-semibold text-ink text-lg tracking-tight">Hotelesy</span>
        </a>
        <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-ink-soft">
            <a href="#platform" class="hover:text-ink">Platform</a>
            <a href="#features" class="hover:text-ink">Features</a>
            <a href="#solutions" class="hover:text-ink">Solutions</a>
            <a href="#customers" class="hover:text-ink">Customers</a>
            <a href="#pricing" class="hover:text-ink">Pricing</a>
        </nav>
        <div class="flex items-center gap-3">
            <a href="{{ route('login') }}" class="hidden sm:inline-flex text-sm font-medium text-ink-soft hover:text-ink">Sign in</a>
            <a href="#contact" class="inline-flex items-center gap-1.5 bg-brand hover:bg-brand-deep text-white text-sm font-semibold px-4 py-2 rounded-lg transition shadow-pop">
                Book a demo
            </a>
        </div>
    </div>
</header>

<main>{{ $slot }}</main>

{{-- ===== FOOTER ===== --}}
<footer class="border-t border-line bg-white">
    <div class="max-w-7xl mx-auto px-6 py-16">
        <div class="grid md:grid-cols-5 gap-8">
            <div class="md:col-span-1">
                <div class="flex items-center gap-2.5 mb-4">
                    <div class="w-8 h-8 rounded-lg bg-brand text-white font-bold flex items-center justify-center text-base">h</div>
                    <span class="font-semibold text-ink text-lg">Hotelesy</span>
                </div>
                <p class="text-sm text-ink-soft">The operating system for modern hotels.</p>
            </div>
            @php
                $cols = [
                    'Platform' => ['Cloud PMS','Channel Manager','Booking Engine','Restaurant POS','Revenue Copilot','Guest Experience'],
                    'Solutions'=> ['Independent Hotels','Hotel Groups','Resorts','Boutique & Villas','Service Apartments','Hostels'],
                    'Resources'=> ['Customer stories','Help Center','API documentation','System status','Security','Blog'],
                    'Company'  => ['About','Careers','Press','Partners','Contact','Sitemap'],
                ];
            @endphp
            @foreach($cols as $heading => $links)
                <div>
                    <div class="text-xs uppercase tracking-widest text-ink font-semibold mb-3">{{ $heading }}</div>
                    <ul class="space-y-2 text-sm">
                        @foreach($links as $l)
                            <li><a href="#" class="text-ink-soft hover:text-ink">{{ $l }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
        <div class="border-t border-line mt-12 pt-6 flex flex-col md:flex-row items-center justify-between gap-3 text-xs text-ink-mute">
            <div>© {{ date('Y') }} Hotelesy Technologies Pvt. Ltd. · All rights reserved.</div>
            <div class="flex items-center gap-5">
                <a href="#" class="hover:text-ink">Privacy</a>
                <a href="#" class="hover:text-ink">Terms</a>
                <a href="#" class="hover:text-ink">DPA</a>
                <a href="#" class="hover:text-ink">Cookies</a>
            </div>
        </div>
    </div>
</footer>

@livewireScripts
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
