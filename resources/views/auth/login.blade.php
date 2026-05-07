<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in · Hotelesy by WebSenor</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','ui-sans-serif','system-ui'] }, colors: { brand: {500:'#5663f5',600:'#3f48dc',700:'#3239b0',800:'#272d8a'}}}}}
    </script>
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-brand-50 to-slate-100 flex items-center justify-center p-6">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center text-white font-bold text-xl shadow-lg">H</div>
                <div class="text-left">
                    <div class="font-bold text-slate-900 text-lg leading-tight">Hotelesy <span class="text-xs font-normal text-slate-500">by WebSenor</span></div>
                    <div class="text-xs text-slate-500">Multi-property hotel ERP</div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-8">
            <h1 class="text-2xl font-bold text-slate-900 mb-1">Welcome back</h1>
            <p class="text-sm text-slate-600 mb-6">Sign in to your hotel's dashboard.</p>

            @if($errors->any())
                <div class="mb-5 rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-800">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ url('login') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Email address</label>
                    <input type="email" name="email" value="{{ old('email', 'admin@miraj-demo.test') }}" required autofocus
                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Password</label>
                    <input type="password" name="password" value="password" required
                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition">
                </div>
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="remember" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        Remember me
                    </label>
                </div>
                <button type="submit" class="w-full bg-gradient-to-br from-brand-500 to-brand-700 hover:from-brand-600 hover:to-brand-800 text-white font-semibold py-2.5 rounded-lg transition shadow-md hover:shadow-lg">
                    Sign in
                </button>
            </form>

            <div class="mt-6 pt-6 border-t border-slate-200">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Demo accounts</div>
                <ul class="text-xs text-slate-600 space-y-1">
                    <li><code class="text-slate-800 font-mono">admin@miraj-demo.test</code> · Owner / Director</li>
                    <li><code class="text-slate-800 font-mono">fom@miraj-demo.test</code> · Front Office Manager</li>
                    <li><code class="text-slate-800 font-mono">cashier@miraj-demo.test</code> · Cashier</li>
                </ul>
                <div class="text-xs text-slate-500 mt-2">All passwords: <code class="font-mono">password</code></div>
            </div>
        </div>

        <div class="text-center mt-6 text-xs text-slate-500">
            © {{ date('Y') }} Hotelesy by WebSenor
        </div>
    </div>
</body>
</html>
