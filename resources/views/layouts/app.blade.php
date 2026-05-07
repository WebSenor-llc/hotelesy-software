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
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui'] },
            colors: {
              brand: {
                50: '#f5f7ff', 100: '#e9eeff', 200: '#cbd5ff',
                300: '#a4b3ff', 400: '#7a89ff', 500: '#5663f5',
                600: '#3f48dc', 700: '#3239b0', 800: '#272d8a', 900: '#1d2168'
              }
            }
          }
        }
      }
    </script>
    <style>
      body { font-family: 'Inter', ui-sans-serif, system-ui; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased min-h-screen">
    <header class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center text-white font-bold">H</div>
                <div>
                    <div class="font-bold text-slate-900 leading-tight">Hotelesy <span class="text-xs font-normal text-slate-500">by WebSenor</span></div>
                    <div class="text-xs text-slate-500 leading-tight">Multi-tenant hotel ERP</div>
                </div>
            </a>
            <nav class="flex items-center gap-6 text-sm">
                <a href="{{ route('home') }}" class="text-slate-700 hover:text-brand-600">Overview</a>
                <a href="{{ route('modules') }}" class="text-slate-700 hover:text-brand-600">Modules</a>
                <a href="{{ route('health') }}" class="text-slate-700 hover:text-brand-600">Health</a>
                <span class="px-3 py-1.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">{{ config('app.env') }}</span>
            </nav>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 py-10">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <footer class="border-t border-slate-200 mt-16">
        <div class="max-w-7xl mx-auto px-6 py-6 text-xs text-slate-500 flex justify-between">
            <div>© {{ date('Y') }} Hotelesy by WebSenor</div>
            <div>Laravel {{ app()->version() }} · PHP {{ PHP_VERSION }}</div>
        </div>
    </footer>
</body>
</html>
