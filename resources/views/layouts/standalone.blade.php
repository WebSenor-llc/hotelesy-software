<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Hotelesy by WebSenor' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','ui-sans-serif','system-ui'] }, colors: { brand: {50:'#f5f7ff',100:'#e9eeff',200:'#cbd5ff',300:'#a4b3ff',400:'#7a89ff',500:'#5663f5',600:'#3f48dc',700:'#3239b0',800:'#272d8a',900:'#1d2168'}}}}}
    </script>
    <style> body { font-family: 'Inter', sans-serif; } </style>
    @livewireStyles
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-brand-50 to-slate-100 flex items-center justify-center p-6">
    <div class="w-full max-w-2xl">
        <div class="text-center mb-6">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center text-white font-bold text-xl shadow-lg">H</div>
                <div class="text-left">
                    <div class="font-bold text-slate-900 text-lg leading-tight">Hotelesy <span class="text-xs font-normal text-slate-500">by WebSenor</span></div>
                    <div class="text-xs text-slate-500">Multi-property hotel ERP</div>
                </div>
            </a>
        </div>

        {{ $slot ?? '' }}

        <div class="text-center mt-6 text-xs text-slate-500">
            &copy; {{ date('Y') }} Hotelesy by WebSenor
        </div>
    </div>
@livewireScripts
</body>
</html>
