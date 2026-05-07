<div>
{{-- ====== HERO ====== --}}
<section class="relative bg-gradient-hero overflow-hidden">
    <div class="absolute inset-0 grid-bg opacity-60 pointer-events-none"></div>
    <div class="relative max-w-7xl mx-auto px-6 pt-16 pb-24">

        {{-- New pill --}}
        <div class="flex justify-center mb-8 animate-fadeup" style="animation-delay:.05s">
            <a href="#features" class="inline-flex items-center gap-2 bg-white border border-line rounded-full pl-1 pr-4 py-1 text-sm shadow-card hover:border-brand/40 transition">
                <span class="bg-brand text-white text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full">New</span>
                <span class="text-ink-soft">AI Revenue Copilot is live <span class="text-ink">→</span></span>
            </a>
        </div>

        {{-- H1 --}}
        <h1 class="text-center font-semibold tracking-tight text-ink text-5xl md:text-6xl lg:text-7xl leading-[1.05] max-w-5xl mx-auto animate-fadeup" style="animation-delay:.1s">
            The operating system for
            <span class="font-serif italic text-brand"> modern hotels</span>
        </h1>

        <p class="text-center text-ink-soft text-lg md:text-xl mt-6 max-w-3xl mx-auto leading-relaxed animate-fadeup" style="animation-delay:.15s">
            Hotelesy unifies your PMS, channel manager, booking engine, POS and revenue intelligence in one beautiful cloud platform — so your team runs the hotel, not the software.
        </p>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-3 mt-9 animate-fadeup" style="animation-delay:.2s">
            <a href="{{ route('trial.start') }}" class="inline-flex items-center gap-2 bg-brand hover:bg-brand-deep text-white font-semibold px-6 py-3.5 rounded-lg shadow-pop transition">
                Start 30-day free trial <span>→</span>
            </a>
            <a href="#" class="inline-flex items-center gap-2 bg-white hover:bg-subtle text-ink font-semibold px-6 py-3.5 rounded-lg border border-line transition">
                <span class="w-5 h-5 rounded-full bg-ink text-white flex items-center justify-center text-[10px]">▶</span>
                Watch the 2-min tour
            </a>
        </div>

        <div class="flex items-center justify-center gap-2 mt-7 text-sm text-ink-soft animate-fadeup" style="animation-delay:.3s">
            <div class="flex items-center gap-0.5 text-brand">
                @for($i=0;$i<5;$i++)<span>★</span>@endfor
            </div>
            <span><strong class="text-ink">4.9/5</strong> from 1,200+ hoteliers · No credit card required</span>
        </div>

        {{-- Hero product image card --}}
        <div class="relative mt-16 max-w-6xl mx-auto animate-fadeup" style="animation-delay:.4s">
            {{-- glow --}}
            <div class="absolute -inset-4 bg-gradient-brand opacity-20 blur-3xl rounded-[2rem]"></div>

            {{-- product card --}}
            <div class="relative bg-white rounded-2xl border border-line shadow-product overflow-hidden">
                <div class="px-4 py-3 bg-subtle border-b border-line flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-300"></span>
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-300"></span>
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-300"></span>
                    <span class="ml-3 text-[11px] text-ink-mute font-mono">app.hotelesy.com / dashboard</span>
                </div>
                <div class="p-6 md:p-8 grid grid-cols-12 gap-4">
                    {{-- Sidebar --}}
                    <div class="col-span-3 hidden md:block">
                        <div class="space-y-1 text-xs">
                            @foreach(['Dashboard','Reservations','Tape chart','Front office','Channel manager','Revenue','POS','Reports'] as $i => $item)
                                <div class="flex items-center gap-2 px-2.5 py-1.5 rounded {{ $i===0 ? 'bg-brand/10 text-brand-deep font-semibold' : 'text-ink-soft' }}">
                                    <span class="w-1 h-1 rounded-full {{ $i===0 ? 'bg-brand' : 'bg-line' }}"></span>{{ $item }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                    {{-- Main panel --}}
                    <div class="col-span-12 md:col-span-9">
                        <div class="grid grid-cols-3 gap-3 mb-4">
                            <div class="bg-subtle rounded-xl p-3.5">
                                <div class="text-[10px] uppercase tracking-wider text-ink-soft font-semibold">Occupancy</div>
                                <div class="text-2xl font-semibold text-ink">86%</div>
                                <div class="text-[10px] text-success font-semibold mt-0.5">↑ 4.2 pts</div>
                            </div>
                            <div class="bg-subtle rounded-xl p-3.5">
                                <div class="text-[10px] uppercase tracking-wider text-ink-soft font-semibold">RevPAR</div>
                                <div class="text-2xl font-semibold text-ink">₹4,820</div>
                                <div class="text-[10px] text-success font-semibold mt-0.5">↑ 18%</div>
                            </div>
                            <div class="bg-subtle rounded-xl p-3.5">
                                <div class="text-[10px] uppercase tracking-wider text-ink-soft font-semibold">ADR</div>
                                <div class="text-2xl font-semibold text-ink">₹5,610</div>
                                <div class="text-[10px] text-success font-semibold mt-0.5">↑ 6.8%</div>
                            </div>
                        </div>
                        <div class="bg-subtle rounded-xl p-4">
                            <div class="text-[10px] uppercase tracking-wider text-ink-soft font-semibold mb-3">Pickup pace · 14 days</div>
                            <div class="flex items-end gap-1.5 h-24">
                                @foreach([35,42,38,55,62,71,68,74,82,76,88,91,86,93] as $h)
                                    <div class="flex-1 bg-gradient-to-t from-brand to-brand/40 rounded-sm" style="height: {{ $h }}%"></div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Floating cards --}}
            <div class="hidden md:block absolute -top-6 -right-6 bg-white rounded-xl border border-line shadow-card px-4 py-3 animate-float">
                <div class="text-[10px] uppercase tracking-wider text-ink-soft font-semibold">RevPAR today</div>
                <div class="flex items-baseline gap-2">
                    <span class="text-xl font-semibold text-ink">₹12,492</span>
                    <span class="text-xs text-success font-semibold">+18%</span>
                </div>
            </div>
            <div class="hidden md:block absolute -bottom-6 -left-6 bg-white rounded-xl border border-line shadow-card px-4 py-3 animate-float" style="animation-delay:1.5s">
                <div class="flex items-center gap-1.5 mb-0.5">
                    <span class="w-2 h-2 rounded-full bg-success animate-pulse"></span>
                    <span class="text-[10px] uppercase tracking-wider text-ink-soft font-semibold">Live bookings</span>
                </div>
                <div class="text-xl font-semibold text-ink">14 today</div>
            </div>
        </div>
    </div>
</section>

{{-- ====== LOGO CLOUD ====== --}}
<section class="border-y border-line bg-white py-12 overflow-hidden">
    <p class="text-center text-sm text-ink-soft mb-7">Trusted by 2,400+ properties across 47 countries</p>
    <div class="relative">
        <div class="absolute inset-y-0 left-0 w-32 bg-gradient-to-r from-white to-transparent z-10 pointer-events-none"></div>
        <div class="absolute inset-y-0 right-0 w-32 bg-gradient-to-l from-white to-transparent z-10 pointer-events-none"></div>
        <div class="marquee-track">
            @foreach([1,2] as $loop)
                @foreach(['TAJ','OBEROI','MARRIOTT','LEELA','RADISSON','HYATT','ACCOR','ITC','FOUR SEASONS','AMAN'] as $logo)
                    <div class="px-10 text-2xl font-semibold tracking-[0.25em] text-ink/30 whitespace-nowrap">{{ $logo }}</div>
                @endforeach
            @endforeach
        </div>
    </div>
</section>

{{-- ====== STATS STRIP ====== --}}
<section class="bg-subtle border-b border-line">
    <div class="max-w-7xl mx-auto px-6 py-16 grid grid-cols-2 md:grid-cols-4 gap-y-10 gap-x-6">
        @foreach([
            ['+22%','average RevPAR lift','in the first 90 days'],
            ['18 hrs','saved per week','across front office & revenue'],
            ['99.99%','platform uptime','SLA-backed, 4 global regions'],
            ['11 days','average go-live','with white-glove migration'],
        ] as $s)
            <div>
                <div class="text-5xl md:text-6xl font-semibold text-brand tracking-tight">{{ $s[0] }}</div>
                <div class="text-ink font-medium mt-1">{{ $s[1] }}</div>
                <div class="text-sm text-ink-soft">{{ $s[2] }}</div>
            </div>
        @endforeach
    </div>
</section>

{{-- ====== FEATURES (10 tiles) ====== --}}
<section id="features" class="bg-white py-24 md:py-32">
    <div class="max-w-7xl mx-auto px-6">
        <div class="text-center max-w-3xl mx-auto mb-16">
            <p class="text-xs uppercase tracking-[0.25em] text-brand font-semibold mb-3">The platform</p>
            <h2 class="font-semibold text-ink text-4xl md:text-5xl tracking-tight leading-[1.1]">
                Everything you need to run a hotel.
                <span class="font-serif italic text-brand">Nothing you don't.</span>
            </h2>
            <p class="text-ink-soft mt-5 text-lg">Replace seven disconnected tools with one beautifully designed platform that your team actually loves.</p>
        </div>

        @php
            $features = [
                ['Cloud PMS','Tape chart, folios, group blocks, night audit — all in a snappy modern interface.','◰'],
                ['Channel Manager','Real-time 2-way sync with 200+ OTAs. Zero overbookings, ever.','⚭'],
                ['Revenue Intelligence','AI yield management with competitor pricing, demand forecasting and auto-publish.','↗'],
                ['Booking Engine','Commission-free direct bookings with a branded engine that converts 2× better.','◴'],
                ['Restaurant POS','F&B, bar, spa, minibar — one tap to post charges to the room folio.','🍴'],
                ['Guest Experience','Pre-arrival upsells, digital check-in, in-stay messaging and review automation.','✦'],
                ['Housekeeping','Live room status, mobile checklists and predictive cleaning schedules.','◇'],
                ['Payments','Tokenised cards, split folios, GST invoicing and reconciliation on autopilot.','₹'],
                ['AI Copilot','Ask anything about your hotel and get instant answers, drafts and reports.','✺'],
                ['Enterprise security','SOC 2 Type II, PCI-DSS Level 1, SSO, granular roles and audit logs.','◆'],
            ];
        @endphp
        <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-px bg-line border border-line rounded-2xl overflow-hidden">
            @foreach($features as $f)
                <div class="group bg-white hover:bg-subtle p-6 transition cursor-pointer">
                    <div class="w-10 h-10 rounded-lg bg-brand-soft text-brand-deep group-hover:bg-brand group-hover:text-white flex items-center justify-center text-base font-semibold transition">{{ $f[2] }}</div>
                    <h3 class="font-semibold text-ink mt-4">{{ $f[0] }}</h3>
                    <p class="text-sm text-ink-soft mt-1.5 leading-relaxed">{{ $f[1] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ====== PLATFORM DEEP-DIVES (3 alternating blocks) ====== --}}
<section id="platform" class="bg-subtle border-y border-line py-24 md:py-32">
    <div class="max-w-7xl mx-auto px-6 space-y-24">
        @php
            $blocks = [
                [
                    'eyebrow' => 'Revenue Intelligence',
                    'icon'    => '↗',
                    'title'   => 'The right rate, every single night.',
                    'body'    => 'Our AI ingests demand signals, competitor pricing, weather, events and historical pickup to recommend rates that maximise RevPAR — automatically.',
                    'bullets' => ['Live competitor benchmarking','Forecast accuracy within 3%','One-click rate publishing'],
                    'flip'    => false,
                    'visual'  => 'rate',
                ],
                [
                    'eyebrow' => 'Front Office',
                    'icon'    => '⇄',
                    'title'   => 'Check guests in, in under 30 seconds.',
                    'body'    => 'A reimagined PMS with the fastest tape chart in the industry, built-in identity capture, digital registration cards and contactless key delivery.',
                    'bullets' => ['Drag-and-drop tape chart','Digital registration & e-signatures','Mobile check-in & key sharing'],
                    'flip'    => true,
                    'visual'  => 'tape',
                ],
                [
                    'eyebrow' => 'Guest Experience',
                    'icon'    => '✦',
                    'title'   => 'Guests who feel seen, come back.',
                    'body'    => 'Personalised pre-arrival emails, smart upsell offers, in-stay chat across WhatsApp & SMS, and post-stay review automation — all in one inbox.',
                    'bullets' => ['WhatsApp Business integration','Auto-segmented upsell offers','Net Promoter Score tracking'],
                    'flip'    => false,
                    'visual'  => 'inbox',
                ],
            ];
        @endphp
        @foreach($blocks as $b)
            <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center {{ $b['flip'] ? 'lg:[direction:rtl]' : '' }}">
                <div class="lg:[direction:ltr]">
                    <div class="inline-flex items-center gap-2 bg-brand-soft text-brand-deep text-xs font-semibold px-3 py-1 rounded-full">
                        <span>{{ $b['icon'] }}</span>
                        <span class="uppercase tracking-wider">{{ $b['eyebrow'] }}</span>
                    </div>
                    <h2 class="font-semibold text-ink text-3xl md:text-4xl lg:text-5xl tracking-tight mt-5 leading-[1.1]">{{ $b['title'] }}</h2>
                    <p class="text-ink-soft mt-5 text-lg leading-relaxed">{{ $b['body'] }}</p>
                    <ul class="mt-6 space-y-3">
                        @foreach($b['bullets'] as $bullet)
                            <li class="flex items-start gap-3">
                                <span class="w-5 h-5 rounded-full bg-brand text-white flex items-center justify-center text-[11px] font-bold flex-shrink-0 mt-0.5">✓</span>
                                <span class="text-ink">{{ $bullet }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <a href="#" class="inline-flex items-center gap-1.5 text-brand-deep font-semibold mt-7 hover:gap-2.5 transition-all">
                        Learn more about {{ $b['eyebrow'] }} <span>→</span>
                    </a>
                </div>
                <div class="lg:[direction:ltr] relative">
                    <div class="absolute -inset-4 bg-gradient-brand opacity-15 blur-3xl rounded-[2rem]"></div>
                    <div class="relative bg-white rounded-2xl border border-line shadow-product overflow-hidden p-6">
                        @if($b['visual'] === 'rate')
                            {{-- Rate AI panel --}}
                            <div class="text-[10px] uppercase tracking-wider text-ink-soft font-semibold mb-3">Tonight's recommended rate</div>
                            <div class="flex items-baseline gap-3 mb-4">
                                <span class="text-5xl font-semibold text-ink">₹8,450</span>
                                <span class="text-success font-semibold text-sm">↑ ₹920 vs yesterday</span>
                            </div>
                            <div class="grid grid-cols-7 gap-1 h-24 mb-4">
                                @foreach([62,68,72,78,82,89,94] as $i => $h)
                                    <div class="flex flex-col justify-end items-center gap-1">
                                        <div class="w-full bg-gradient-to-t from-brand to-brand/40 rounded-t" style="height: {{ $h }}%"></div>
                                        <span class="text-[9px] text-ink-mute">{{ ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'][$i] }}</span>
                                    </div>
                                @endforeach
                            </div>
                            <div class="bg-brand-soft border border-brand/30 rounded-lg p-3 text-xs">
                                <div class="font-semibold text-brand-deep mb-1">✺ AI insight</div>
                                <div class="text-ink-soft">Local conference Friday — competitors raised 12%. Recommend +₹920 (DLX, PRM).</div>
                            </div>
                        @elseif($b['visual'] === 'tape')
                            {{-- Tape chart --}}
                            <div class="text-[10px] uppercase tracking-wider text-ink-soft font-semibold mb-3">Tape chart · this week</div>
                            <div class="space-y-1">
                                @for($r=0; $r<6; $r++)
                                    <div class="flex items-center gap-1">
                                        <div class="w-12 text-xs text-ink-soft">{{ 101 + $r }}</div>
                                        <div class="flex-1 grid grid-cols-7 gap-1">
                                            @for($c=0; $c<7; $c++)
                                                @php $occ = ($r+$c) % 4; @endphp
                                                <div class="h-6 rounded {{ $occ === 0 ? 'bg-brand/80' : ($occ === 1 ? 'bg-brand/40' : ($occ === 2 ? 'bg-emerald-100' : 'bg-subtle')) }}"></div>
                                            @endfor
                                        </div>
                                    </div>
                                @endfor
                            </div>
                            <div class="flex items-center gap-3 text-[10px] text-ink-soft mt-3">
                                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-brand/80"></span>Occupied</span>
                                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-brand/40"></span>Confirmed</span>
                                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-emerald-100"></span>Vacant clean</span>
                            </div>
                        @elseif($b['visual'] === 'inbox')
                            {{-- Inbox --}}
                            <div class="text-[10px] uppercase tracking-wider text-ink-soft font-semibold mb-3">Guest inbox</div>
                            <div class="space-y-2">
                                @foreach([['M','Mira Sharma','Late check-in tonight, room 305?','WhatsApp','2m'],['A','Arjun K.','Loved the welcome amenity 🙏','SMS','12m'],['S','Sara Iyer','Can we extend by 1 night?','WhatsApp','1h']] as $msg)
                                    <div class="flex items-start gap-3 p-2.5 hover:bg-subtle rounded-lg cursor-pointer">
                                        <div class="w-8 h-8 rounded-full bg-gradient-brand text-white flex items-center justify-center text-xs font-semibold">{{ $msg[0] }}</div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="font-semibold text-ink">{{ $msg[1] }}</span>
                                                <span class="text-ink-mute">{{ $msg[4] }}</span>
                                            </div>
                                            <div class="text-xs text-ink-soft truncate">{{ $msg[2] }}</div>
                                            <span class="inline-block mt-1 text-[9px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-brand-soft text-brand-deep font-semibold">{{ $msg[3] }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>

{{-- ====== SOLUTIONS ====== --}}
<section id="solutions" class="bg-white py-24 md:py-32">
    <div class="max-w-7xl mx-auto px-6">
        <div class="text-center max-w-3xl mx-auto mb-16">
            <p class="text-xs uppercase tracking-[0.25em] text-brand font-semibold mb-3">Solutions</p>
            <h2 class="font-semibold text-ink text-4xl md:text-5xl tracking-tight leading-[1.1]">Built for every kind of stay.</h2>
            <p class="text-ink-soft mt-5 text-lg">From a 12-room boutique to a 50-property group — Hotelesy scales with you.</p>
        </div>
        @php
            $solutions = [
                ['Independent Hotels','10–80 rooms · A complete stack out of the box.','🏨'],
                ['Hotel Groups','Multi-property dashboards, shared inventory & central rates.','🏢'],
                ['Resorts & Retreats','Activities, packages, F&B, spa — sold and tracked together.','🌴'],
                ['Boutique & Villas',"Designed for owner-operators who care about every detail.",'⌂'],
                ['Service Apartments','Long-stay rates, monthly billing, corporate accounts.','◫'],
                ['Hostels','Bed-level inventory, dorm management and social features.','▤'],
            ];
        @endphp
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($solutions as $s)
                <a href="#" class="group block bg-white rounded-2xl border border-line p-6 hover:border-brand hover:-translate-y-1 hover:shadow-product transition duration-300">
                    <div class="w-12 h-12 rounded-xl bg-gradient-brand text-white flex items-center justify-center text-xl shadow-pop">{{ $s[2] }}</div>
                    <h3 class="font-semibold text-ink mt-5 text-lg">{{ $s[0] }}</h3>
                    <p class="text-sm text-ink-soft mt-2 leading-relaxed">{{ $s[1] }}</p>
                    <div class="mt-4 inline-flex items-center gap-1 text-sm text-brand-deep font-semibold opacity-0 group-hover:opacity-100 transition">
                        Explore <span>→</span>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- ====== INTEGRATIONS ====== --}}
<section class="bg-subtle border-y border-line py-24 md:py-32">
    <div class="max-w-7xl mx-auto px-6 grid lg:grid-cols-[1fr_1.4fr] gap-12 lg:gap-16 items-center">
        <div>
            <p class="text-xs uppercase tracking-[0.25em] text-brand font-semibold mb-3">Integrations</p>
            <h2 class="font-semibold text-ink text-4xl md:text-5xl tracking-tight leading-[1.1]">Connects to everything you already use.</h2>
            <p class="text-ink-soft mt-5 text-lg leading-relaxed">200+ native integrations across distribution, payments, accounting, marketing and guest messaging — plus an open REST API and webhooks.</p>
            <a href="#" class="inline-flex items-center gap-1.5 text-brand-deep font-semibold mt-6 hover:gap-2.5 transition-all">Browse all integrations <span>→</span></a>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            @foreach(['Booking.com','Expedia','Airbnb','MakeMyTrip','Goibibo','Agoda','Stripe','Razorpay','QuickBooks','Tally','Mailchimp','WhatsApp','Google Hotel Ads','TripAdvisor','Cloudbeds','Salesforce'] as $integration)
                <div class="bg-white rounded-xl border border-line px-4 py-5 text-center text-sm font-semibold text-ink-soft hover:border-brand hover:text-brand-deep transition">{{ $integration }}</div>
            @endforeach
        </div>
    </div>
</section>

{{-- ====== TESTIMONIALS ====== --}}
<section id="customers" class="bg-white py-24 md:py-32">
    <div class="max-w-7xl mx-auto px-6">
        <div class="text-center max-w-3xl mx-auto mb-16">
            <p class="text-xs uppercase tracking-[0.25em] text-brand font-semibold mb-3">Customers</p>
            <h2 class="font-semibold text-ink text-4xl md:text-5xl tracking-tight leading-[1.1]">Loved by the people who run hotels every day.</h2>
        </div>

        <div class="grid lg:grid-cols-7 gap-5">
            {{-- Hero quote spans 3 cols × 2 rows --}}
            <div class="lg:col-span-3 lg:row-span-2 bg-gradient-dark text-white rounded-2xl p-8 md:p-10 relative overflow-hidden">
                <div class="absolute -top-16 -right-16 w-64 h-64 rounded-full bg-brand/30 blur-3xl"></div>
                <div class="relative">
                    <div class="flex gap-0.5 text-brand text-lg">@for($i=0;$i<5;$i++)<span>★</span>@endfor</div>
                    <p class="font-serif italic text-2xl md:text-3xl mt-5 leading-snug">"We replaced four systems with Hotelesy and recovered 18% of staff time in the first quarter. RevPAR is up 22% year over year. The team genuinely enjoys using it."</p>
                    <div class="flex items-center gap-3 mt-8 pt-6 border-t border-white/10">
                        <div class="w-12 h-12 rounded-full bg-brand text-white flex items-center justify-center font-semibold">AM</div>
                        <div>
                            <div class="font-semibold">Aanya Mehta</div>
                            <div class="text-sm text-white/60">General Manager · Lake Palace Udaipur</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 4 small cards --}}
            @php
                $smalls = [
                    ['"Onboarding took eleven days across nine properties. Extraordinary migration team."','Priya Raghavan','COO, Heritage Hotels'],
                    ['"The AI rate copilot has fundamentally changed how we think about pricing."','Daniel Park','Director of Revenue, Coastal Collection'],
                    ['"Our front desk check-in is now 4× faster. Guests notice on day one."','Marcus Chen','Front Office Manager, The Madison'],
                    ['"Direct bookings doubled within three months. Worth every rupee."','Sara Iyer','Owner, Casa Verde Boutique'],
                ];
            @endphp
            @foreach($smalls as $t)
                <div class="lg:col-span-2 bg-subtle rounded-2xl border border-line p-6">
                    <div class="flex gap-0.5 text-brand text-sm">@for($i=0;$i<5;$i++)<span>★</span>@endfor</div>
                    <p class="text-ink mt-3 leading-relaxed text-sm">{{ $t[0] }}</p>
                    <div class="mt-4 pt-4 border-t border-line">
                        <div class="text-sm font-semibold text-ink">{{ $t[1] }}</div>
                        <div class="text-xs text-ink-soft">{{ $t[2] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ====== PRICING ====== --}}
<section id="pricing" class="bg-subtle border-y border-line py-24 md:py-32">
    <div class="max-w-7xl mx-auto px-6">
        <div class="text-center max-w-3xl mx-auto mb-16">
            <p class="text-xs uppercase tracking-[0.25em] text-brand font-semibold mb-3">Pricing</p>
            <h2 class="font-semibold text-ink text-4xl md:text-5xl tracking-tight leading-[1.1]">Honest pricing. <span class="font-serif italic text-brand">Per property.</span></h2>
            <p class="text-ink-soft mt-5 text-lg">No commission on bookings. No per-user fees. Cancel anytime.</p>
            <div class="inline-flex items-center bg-white border border-line rounded-full p-1 mt-6">
                <button wire:click="setBilling('monthly')" class="px-5 py-2 text-sm font-semibold rounded-full transition {{ $billingToggle === 'monthly' ? 'bg-ink text-white' : 'text-ink-soft' }}">Monthly</button>
                <button wire:click="setBilling('yearly')" class="px-5 py-2 text-sm font-semibold rounded-full transition relative {{ $billingToggle === 'yearly' ? 'bg-ink text-white' : 'text-ink-soft' }}">
                    Yearly
                    <span class="absolute -top-2 -right-2 bg-brand text-white text-[9px] uppercase tracking-wider font-bold px-2 py-0.5 rounded-full">−17%</span>
                </button>
            </div>
        </div>

        @php
            $tiers = [
                [
                    'name' => 'Boutique',
                    'price_monthly' => 6900,
                    'unit' => 'per property / month',
                    'desc' => 'For independents up to 30 rooms.',
                    'cta'  => 'Start free trial',
                    'featured' => false,
                    'features' => [
                        'Cloud PMS & booking engine',
                        'Channel manager · 50 OTAs',
                        'Payments & folios',
                        'Email support · business hours',
                    ],
                ],
                [
                    'name' => 'Collection',
                    'price_monthly' => 14900,
                    'unit' => 'per property / month',
                    'desc' => 'For growing groups and resorts.',
                    'cta'  => 'Start free trial',
                    'featured' => true,
                    'features' => [
                        'Everything in Boutique',
                        'Restaurant, bar & spa POS',
                        'AI Revenue Copilot',
                        'Multi-property dashboards',
                        'Priority 24/7 support',
                    ],
                ],
                [
                    'name' => 'Enterprise',
                    'price_monthly' => null,
                    'unit' => 'tailored to your portfolio',
                    'desc' => 'For chains and global brands.',
                    'cta'  => 'Talk to sales',
                    'featured' => false,
                    'features' => [
                        'Custom integrations & SSO',
                        'Dedicated success team',
                        'SLA-backed uptime',
                        'White-glove onboarding',
                        'Advanced security & DPA',
                    ],
                ],
            ];
        @endphp
        <div class="grid md:grid-cols-3 gap-5">
            @foreach($tiers as $t)
                @php
                    $price = $t['price_monthly'];
                    if ($price && $billingToggle === 'yearly') $price = round($price * 0.83);
                @endphp
                <div class="relative {{ $t['featured'] ? 'lg:-mt-4' : '' }}">
                    @if($t['featured'])
                        <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-brand text-white text-[10px] uppercase tracking-widest font-bold px-3 py-1 rounded-full shadow-pop">Most popular</div>
                    @endif
                    <div class="bg-white rounded-2xl {{ $t['featured'] ? 'border-2 border-brand shadow-pop' : 'border border-line' }} p-7 h-full flex flex-col">
                        <div class="font-semibold text-ink text-2xl">{{ $t['name'] }}</div>
                        <p class="text-sm text-ink-soft mt-1">{{ $t['desc'] }}</p>
                        <div class="mt-6">
                            @if($price)
                                <div class="flex items-baseline gap-1">
                                    <span class="text-5xl font-semibold text-ink tracking-tight">₹{{ number_format($price) }}</span>
                                </div>
                                <div class="text-sm text-ink-soft mt-1">{{ $t['unit'] }}</div>
                            @else
                                <div class="text-5xl font-semibold text-ink tracking-tight">Custom</div>
                                <div class="text-sm text-ink-soft mt-1">{{ $t['unit'] }}</div>
                            @endif
                        </div>
                        <a href="{{ $t['name'] === 'Enterprise' ? '#contact' : route('trial.start') }}" class="block text-center mt-6 {{ $t['featured'] ? 'bg-brand hover:bg-brand-deep text-white shadow-pop' : 'bg-ink hover:bg-ink-soft text-white' }} font-semibold py-3 rounded-lg transition">{{ $t['cta'] }}</a>
                        <ul class="mt-7 space-y-3 text-sm">
                            @foreach($t['features'] as $f)
                                <li class="flex items-start gap-2.5">
                                    <span class="w-5 h-5 rounded-full bg-brand-soft text-brand-deep flex items-center justify-center text-[11px] font-bold flex-shrink-0 mt-0.5">✓</span>
                                    <span class="text-ink">{{ $f }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endforeach
        </div>
        <p class="text-center text-sm text-ink-soft mt-10">All plans include unlimited users, free OTA migration and 24/7 system monitoring.</p>
    </div>
</section>

{{-- ====== FAQ ====== --}}
<section class="bg-white py-24 md:py-32">
    <div class="max-w-7xl mx-auto px-6 grid lg:grid-cols-[1fr_1.5fr] gap-12 lg:gap-16">
        <div>
            <p class="text-xs uppercase tracking-[0.25em] text-brand font-semibold mb-3">FAQ</p>
            <h2 class="font-semibold text-ink text-4xl md:text-5xl tracking-tight leading-[1.1]">Questions, answered.</h2>
            <p class="text-ink-soft mt-5 text-lg">Can't find what you're looking for? Our team usually replies within 30 minutes.</p>
            <a href="#contact" class="inline-flex items-center gap-1.5 text-brand-deep font-semibold mt-5 hover:gap-2.5 transition-all">Contact us <span>→</span></a>
        </div>
        <div class="space-y-3">
            @php
                $faqs = [
                    ['How long does onboarding take?','Most properties go live within 7–14 days. For groups, parallel migrations typically complete in under 4 weeks. Our team handles inventory import, OTA reconnection and staff training.'],
                    ['Do you charge commission on bookings?','No. Never a cut — direct or OTA. Flat monthly subscription per property, that\'s it.'],
                    ['Can I keep my existing booking engine or channel manager?','Yes. Modules are à la carte and we have an open API plus 200+ native integrations.'],
                    ['Is my guest data secure?','SOC 2 Type II, PCI-DSS Level 1, 4 global regions, 99.99% SLA. Encrypted at rest and in transit.'],
                    ['What kind of support do I get?','Boutique: business-hours email. Collection / Enterprise: 24/7 priority chat, phone + dedicated success manager.'],
                    ['Can Hotelesy handle multiple properties?','Yes — multi-property dashboards, central inventory, group rates and consolidated reporting for 2–500+ hotels.'],
                ];
            @endphp
            @foreach($faqs as $i => $f)
                <details class="group bg-white border border-line rounded-xl open:shadow-card transition" {{ $i === 0 ? 'open' : '' }}>
                    <summary class="flex items-center justify-between cursor-pointer px-6 py-5 list-none">
                        <span class="font-semibold text-ink">{{ $f[0] }}</span>
                        <span class="w-6 h-6 rounded-full bg-subtle text-ink-soft flex items-center justify-center text-sm group-open:rotate-45 transition">+</span>
                    </summary>
                    <div class="px-6 pb-5 text-ink-soft leading-relaxed">{{ $f[1] }}</div>
                </details>
            @endforeach
        </div>
    </div>
</section>

{{-- ====== INQUIRY FORM (kept) ====== --}}
<section id="contact" class="bg-subtle border-y border-line py-24">
    <div class="max-w-3xl mx-auto px-6">
        <div class="text-center mb-10">
            <p class="text-xs uppercase tracking-[0.25em] text-brand font-semibold mb-3">Talk to us</p>
            <h2 class="font-semibold text-ink text-4xl md:text-5xl tracking-tight leading-[1.1]">Have questions? We're listening.</h2>
            <p class="text-ink-soft mt-5 text-lg">Tell us about your hotel — we'll set up a personalised demo within 24 hours.</p>
        </div>

        @if($submitted)
            <div class="bg-white border-2 border-brand rounded-2xl p-8 text-center shadow-card">
                <div class="w-14 h-14 rounded-full bg-brand text-white flex items-center justify-center text-3xl mx-auto">✓</div>
                <h3 class="font-semibold text-2xl text-ink mt-5">Thanks! We've received your inquiry.</h3>
                <p class="text-ink-soft mt-2">A team member will reach out shortly. Reference: <span class="font-mono font-semibold text-ink">#LEAD-{{ str_pad((string) $leadId, 5, '0', STR_PAD_LEFT) }}</span></p>
                <button wire:click="$set('submitted', false)" class="mt-5 text-sm text-ink-soft hover:text-ink underline">Submit another</button>
            </div>
        @else
        <form wire:submit="submit" class="bg-white rounded-2xl border border-line p-7 md:p-8 shadow-card space-y-5">
            <div class="grid md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1.5">Your name *</label>
                    <input wire:model="name" type="text" class="w-full px-4 py-3 border border-line rounded-lg focus:outline-none focus:border-brand transition" placeholder="Aanya Mehta">
                    @error('name')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1.5">Email *</label>
                    <input wire:model="email" type="email" class="w-full px-4 py-3 border border-line rounded-lg focus:outline-none focus:border-brand transition" placeholder="you@hotel.com">
                    @error('email')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="grid md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1.5">Phone *</label>
                    <input wire:model="phone" type="tel" class="w-full px-4 py-3 border border-line rounded-lg focus:outline-none focus:border-brand transition" placeholder="+91 98XXXXXXXX">
                    @error('phone')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1.5">Hotel name</label>
                    <input wire:model="hotel_name" type="text" class="w-full px-4 py-3 border border-line rounded-lg focus:outline-none focus:border-brand transition" placeholder="Lake Palace, Udaipur">
                </div>
            </div>
            <div class="grid md:grid-cols-3 gap-5">
                <div>
                    <label class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1.5">City</label>
                    <input wire:model="city" type="text" class="w-full px-4 py-3 border border-line rounded-lg focus:outline-none focus:border-brand transition" placeholder="Udaipur">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1.5">Rooms</label>
                    <input wire:model="rooms_count" type="number" min="1" class="w-full px-4 py-3 border border-line rounded-lg focus:outline-none focus:border-brand transition" placeholder="40">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1.5">Current PMS</label>
                    <input wire:model="current_pms" type="text" class="w-full px-4 py-3 border border-line rounded-lg focus:outline-none focus:border-brand transition" placeholder="Excel · IDS · Cloudbeds">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1.5">Tell us a bit more</label>
                <textarea wire:model="message" rows="3" class="w-full px-4 py-3 border border-line rounded-lg focus:outline-none focus:border-brand transition" placeholder="What are you looking to solve?"></textarea>
            </div>
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-2">
                <p class="text-xs text-ink-soft">By submitting, you agree to our privacy policy.</p>
                <button type="submit" class="inline-flex items-center gap-2 bg-brand hover:bg-brand-deep text-white font-semibold px-6 py-3 rounded-lg transition shadow-pop" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="submit">Submit inquiry</span>
                    <span wire:loading wire:target="submit">Sending…</span>
                    <span>→</span>
                </button>
            </div>
        </form>
        @endif
    </div>
</section>

{{-- ====== FINAL CTA ====== --}}
<section class="bg-gradient-dark text-white relative overflow-hidden">
    <div class="absolute top-1/2 -left-20 w-[500px] h-[500px] rounded-full bg-brand/20 blur-3xl -translate-y-1/2"></div>
    <div class="absolute bottom-0 -right-20 w-[400px] h-[400px] rounded-full bg-brand/15 blur-3xl"></div>
    <div class="absolute inset-0 dot-bg opacity-10"></div>
    <div class="relative max-w-4xl mx-auto px-6 py-24 md:py-32 text-center">
        <h2 class="font-semibold text-4xl md:text-6xl tracking-tight leading-[1.05]">Give your hotel the operating system <span class="font-serif italic text-brand">it deserves.</span></h2>
        <p class="text-white/70 mt-6 text-lg max-w-2xl mx-auto">Join 2,400+ hoteliers running a calmer, more profitable operation. 30-day free trial — no credit card required.</p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3 mt-9">
            <a href="{{ route('trial.start') }}" class="inline-flex items-center gap-2 bg-brand hover:bg-brand-deep text-white font-semibold px-7 py-3.5 rounded-lg shadow-pop transition">Start free trial <span>→</span></a>
            <a href="#contact" class="inline-flex items-center gap-2 bg-white/5 hover:bg-white/10 text-white font-semibold px-7 py-3.5 rounded-lg border border-white/20 backdrop-blur transition">Book a private demo</a>
        </div>
        <div class="mt-10 flex flex-wrap items-center justify-center gap-x-8 gap-y-3 text-sm text-white/60">
            @foreach(['Free OTA migration','11-day average go-live','Cancel anytime','Unlimited users'] as $item)
                <span class="flex items-center gap-1.5"><span class="w-4 h-4 rounded-full bg-white/10 flex items-center justify-center text-[10px]">✓</span>{{ $item }}</span>
            @endforeach
        </div>
    </div>
</section>
</div>
