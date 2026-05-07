<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Select property · Hotelesy by WebSenor</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','ui-sans-serif','system-ui'] }, colors: { brand: {500:'#5663f5',600:'#3f48dc',700:'#3239b0'}}}}}
    </script>
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center p-6">
    <div class="w-full max-w-3xl">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-slate-900 mb-1">Select a property</h1>
            <p class="text-sm text-slate-600">Choose which property you'd like to work with.</p>
        </div>

        @if(session('warning'))
            <div class="mb-5 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">{{ session('warning') }}</div>
        @endif

        <div class="grid md:grid-cols-2 gap-4">
            @forelse($properties as $p)
                <form method="POST" action="{{ route('property.switch') }}" class="contents">
                    @csrf
                    <input type="hidden" name="property_id" value="{{ $p->id }}">
                    <button type="submit" class="text-left bg-white rounded-xl border border-slate-200 p-5 hover:border-brand-300 hover:shadow-md transition">
                        <div class="font-semibold text-slate-900 mb-1">{{ $p->name }}</div>
                        <div class="text-xs text-slate-500 mb-3">{{ $p->city }}, {{ $p->country }}</div>
                        <div class="flex items-center justify-between">
                            <code class="text-[10px] text-slate-400 font-mono">{{ $p->code }}</code>
                            <span class="text-xs text-brand-600 font-semibold">Open →</span>
                        </div>
                    </button>
                </form>
            @empty
                <div class="md:col-span-2 bg-white rounded-xl border border-slate-200 p-8 text-center text-slate-500">
                    No properties assigned to your account. Contact your administrator.
                </div>
            @endforelse
        </div>

        <form method="POST" action="{{ url('logout') }}" class="text-center mt-6">
            @csrf
            <button class="text-xs text-slate-500 hover:text-slate-900">Sign out</button>
        </form>
    </div>
</body>
</html>
