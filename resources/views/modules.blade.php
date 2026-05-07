@extends('layouts.app')

@section('content')
<div class="mb-8">
    <h1 class="text-3xl font-bold text-slate-900 mb-2">All modules</h1>
    <p class="text-slate-600">Status of every module in Hotelesy by WebSenor.</p>
</div>

<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200 text-left">
            <tr>
                <th class="px-4 py-3 font-semibold text-slate-700">Category</th>
                <th class="px-4 py-3 font-semibold text-slate-700">Module</th>
                <th class="px-4 py-3 font-semibold text-slate-700">Code</th>
                <th class="px-4 py-3 font-semibold text-slate-700">Description</th>
                <th class="px-4 py-3 font-semibold text-slate-700">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach($modules as $cat)
                @foreach($cat['items'] as $m)
                    @php
                        $badge = match($m['status']) {
                            'ready'      => ['bg-emerald-50 text-emerald-700 border-emerald-200', 'Ready'],
                            'scaffolded' => ['bg-sky-50 text-sky-700 border-sky-200', 'Scaffolded'],
                            'stub'       => ['bg-amber-50 text-amber-700 border-amber-200', 'Stub'],
                            default      => ['bg-slate-100 text-slate-600 border-slate-200', 'Planned'],
                        };
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-slate-500 text-xs uppercase tracking-wider">{{ $cat['label'] }}</td>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $m['name'] }}</td>
                        <td class="px-4 py-3"><code class="text-xs text-slate-500 font-mono">{{ $m['code'] }}</code></td>
                        <td class="px-4 py-3 text-slate-600">{{ $m['desc'] }}</td>
                        <td class="px-4 py-3">
                            <span class="text-[10px] uppercase tracking-wider font-medium px-2 py-0.5 rounded-full border {{ $badge[0] }}">{{ $badge[1] }}</span>
                        </td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</div>
@endsection
