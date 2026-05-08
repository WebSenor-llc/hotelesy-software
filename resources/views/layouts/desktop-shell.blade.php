<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Hotelesy Desktop' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
          theme: {
              extend: {
                  fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui'] },
                  colors: { brand: {
                      50: '#f5f7ff', 100: '#e9eeff', 200: '#cbd5ff', 300: '#a4b3ff',
                      400: '#7a89ff', 500: '#5663f5', 600: '#3f48dc', 700: '#3239b0',
                      800: '#272d8a', 900: '#1d2168'
                  }}
              }
          }
      }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Disable text selection on chrome / drag regions when running inside NativePHP */
        .drag { -webkit-app-region: drag; }
        .nodrag { -webkit-app-region: no-drag; }
    </style>
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">

    {{-- Top title bar — only visible inside the NativePHP window --}}
    <div class="drag h-8 bg-slate-900 text-slate-300 flex items-center px-4 text-[11px] tracking-wide select-none">
        <span class="font-semibold text-white">Hotelesy</span>
        <span class="mx-2 text-slate-500">·</span>
        <span>Desktop edition</span>
        <span class="ml-auto text-slate-500">v{{ config('desktop.version', '1.0.0') }}</span>
    </div>

    {{-- License-status banner (shown for soft_warn / readonly) --}}
    @isset($licenseStatus)
        @if(($licenseStatus['mode'] ?? null) === 'soft_warn')
            <div class="bg-amber-50 border-b border-amber-200 px-4 py-2 text-xs text-amber-900 flex items-center gap-2">
                <span class="font-bold">⚠ Connect to the internet</span>
                <span>Hotelesy hasn't validated your license for {{ $licenseStatus['days_offline'] ?? '?' }} days. Connect to keep using all features.</span>
            </div>
        @elseif(($licenseStatus['mode'] ?? null) === 'readonly')
            <div class="bg-rose-50 border-b border-rose-200 px-4 py-2 text-xs text-rose-900 flex items-center gap-2">
                <span class="font-bold">⛔ Read-only mode</span>
                <span>Offline for {{ $licenseStatus['days_offline'] ?? '?' }} days — please connect to the internet to revalidate. Edits are blocked until then.</span>
            </div>
        @endif
    @endisset

    <main class="min-h-[calc(100vh-2rem)]">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
