<div>
    <div class="flex items-baseline justify-between mb-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Revenue</h1>
            <p class="text-sm text-slate-500">All Hotelesy subscription billing across tenants</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('super.dashboard') }}" class="text-sm text-slate-600">/super</a>
        </div>
    </div>

    {{-- Period filter --}}
    <div class="flex items-center gap-2 mb-4 flex-wrap">
        @foreach([['30','Last 30 days'],['90','Last 90 days'],['365','Last year'],['all','All time']] as $p)
            <button wire:click="$set('period','{{ $p[0] }}')" class="text-xs font-semibold px-3 py-1.5 rounded-full {{ $period === $p[0] ? 'bg-emerald-600 text-white' : 'bg-white border border-slate-300 text-slate-600 hover:bg-slate-50' }}">{{ $p[1] }}</button>
        @endforeach
        <select wire:model.live="statusFilter" class="ml-auto px-3 py-1.5 text-sm border border-slate-300 rounded-lg">
            <option value="all">All statuses</option>
            <option value="captured">Captured</option>
            <option value="pending">Pending</option>
            <option value="failed">Failed</option>
            <option value="refunded">Refunded</option>
        </select>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <div class="bg-emerald-50 rounded-xl border border-emerald-200 p-4"><div class="text-[11px] uppercase tracking-wider text-emerald-700 font-semibold">Captured</div><div class="text-2xl font-bold text-emerald-900">₹{{ number_format($captured, 0) }}</div></div>
        <div class="bg-rose-50 rounded-xl border border-rose-200 p-4"><div class="text-[11px] uppercase tracking-wider text-rose-700 font-semibold">Refunded</div><div class="text-2xl font-bold text-rose-900">₹{{ number_format($refunded, 0) }}</div></div>
        <div class="bg-amber-50 rounded-xl border border-amber-200 p-4"><div class="text-[11px] uppercase tracking-wider text-amber-700 font-semibold">Pending</div><div class="text-2xl font-bold text-amber-900">₹{{ number_format($pending, 0) }}</div></div>
        <div class="bg-slate-100 rounded-xl border border-slate-300 p-4"><div class="text-[11px] uppercase tracking-wider text-slate-700 font-semibold">Failed</div><div class="text-2xl font-bold text-slate-900">₹{{ number_format($failed, 0) }}</div></div>
        <div class="bg-brand-50 rounded-xl border border-brand-300 p-4"><div class="text-[11px] uppercase tracking-wider text-brand-700 font-semibold">Net revenue</div><div class="text-2xl font-bold text-brand-900">₹{{ number_format($netRev, 0) }}</div></div>
    </div>

    {{-- By type --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-5 mb-6">
        <h3 class="font-semibold text-slate-900 mb-3">Captured revenue by type</h3>
        @if($byType->isEmpty())
            <div class="text-sm text-slate-500 py-4 text-center">No captured revenue yet for this period.</div>
        @else
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach($byType as $t)
                <div class="border border-slate-200 rounded-lg p-3">
                    <div class="text-xs uppercase tracking-wider text-slate-500 font-semibold">{{ str_replace('_',' ', $t->type) }}</div>
                    <div class="text-2xl font-bold text-slate-900 mt-1">₹{{ number_format($t->total, 0) }}</div>
                    <div class="text-xs text-slate-500">{{ $t->count }} transactions</div>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Transactions list --}}
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 border-b font-semibold text-slate-900">Transactions ({{ $list->total() }})</div>
        @if($list->total() === 0)
            <div class="p-12 text-center text-sm text-slate-500">No transactions found.</div>
        @else
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                <tr><th class="px-5 py-2 text-left">Invoice</th><th class="px-3 py-2 text-left">Tenant</th><th class="px-3 py-2 text-left">Plan</th><th class="px-3 py-2 text-left">Type</th><th class="px-3 py-2 text-left">Status</th><th class="px-3 py-2 text-left">Method</th><th class="px-3 py-2 text-right">Amount</th><th class="px-3 py-2 text-left">Paid</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($list as $t)
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ $t->invoice_number ?: '—' }}</td>
                    <td class="px-3 py-3"><div class="font-medium text-sm">{{ $t->tenant?->name ?? '—' }}</div></td>
                    <td class="px-3 py-3 text-xs">{{ $t->plan?->name ?? '—' }}</td>
                    <td class="px-3 py-3 text-xs">{{ str_replace('_',' ', $t->type) }}</td>
                    <td class="px-3 py-3"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded {{ $t->statusBadgeClass() }}">{{ $t->status }}</span></td>
                    <td class="px-3 py-3 text-xs">{{ $t->payment_method ?: '—' }}</td>
                    <td class="px-3 py-3 text-right font-mono text-xs">₹{{ number_format($t->amount, 2) }}</td>
                    <td class="px-3 py-3 text-xs text-slate-500">{{ $t->paid_at?->format('d M H:i') ?: '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="p-3 border-t bg-slate-50">{{ $list->links() }}</div>
        @endif
    </div>
</div>
