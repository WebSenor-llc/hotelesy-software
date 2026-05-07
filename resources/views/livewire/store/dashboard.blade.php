<div>
    <div class="flex items-baseline justify-between mb-1">
        <h1 class="text-2xl font-bold">Store / Materials</h1>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="startPurchase" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-1.5 rounded text-sm font-semibold">+ Record purchase / GRN</button>
            <button type="button" wire:click="startIssue" class="bg-amber-600 hover:bg-amber-700 text-white px-4 py-1.5 rounded text-sm font-semibold">− Issue / consume</button>
            <button type="button" wire:click="startAdjust" class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-1.5 rounded text-sm font-semibold">± Adjust</button>
            <button type="button" wire:click="startCreateItem" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-1.5 rounded text-sm font-semibold">+ Item</button>
            <button type="button" wire:click="startCreateVendor" class="bg-slate-600 hover:bg-slate-700 text-white px-4 py-1.5 rounded text-sm font-semibold">+ Vendor</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6">Inventory items, vendors, purchases (GRN), departmental issues/consumption, low-stock alerts.</p>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-sm">{{ session('error') }}</div>@endif

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Items</div><div class="text-2xl font-bold">{{ $stats['items'] }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Vendors</div><div class="text-2xl font-bold">{{ $stats['vendors'] }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Open POs</div><div class="text-2xl font-bold text-amber-600">{{ $stats['open_pos'] }}</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-xs text-slate-500">Low stock items</div><div class="text-2xl font-bold text-rose-600">{{ $stats['low_stock'] }}</div></div>
    </div>

    {{-- Item form --}}
    @if($showItemForm)
        <div class="bg-white rounded-xl border p-6 mb-6 ring-2 ring-brand-200">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold">{{ $editingItemId ? 'Edit item' : 'Add item' }}</h2>
                <button type="button" wire:click="cancelItemForm" class="text-sm text-slate-500">Cancel</button>
            </div>
            <div class="grid md:grid-cols-3 gap-4 mb-4">
                <div><label class="block text-xs font-medium mb-1">Code *</label><input type="text" wire:model="itemCode" class="w-full px-3 py-2 border rounded text-sm uppercase" placeholder="ITEM-001"></div>
                <div class="md:col-span-2"><label class="block text-xs font-medium mb-1">Name *</label><input type="text" wire:model="itemName" class="w-full px-3 py-2 border rounded text-sm"></div>
                <div><label class="block text-xs font-medium mb-1">Category *</label>
                    <select wire:model="itemCategoryId" class="w-full px-3 py-2 border rounded text-sm">
                        <option value="">Select…</option>
                        @foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ $c->type }})</option>@endforeach
                    </select>
                </div>
                <div><label class="block text-xs font-medium mb-1">Unit (UOM)</label>
                    <select wire:model="itemUnit" class="w-full px-3 py-2 border rounded text-sm">
                        <option value="pcs">pcs</option><option value="kg">kg</option><option value="g">g</option>
                        <option value="ltr">ltr</option><option value="ml">ml</option><option value="btl">btl</option>
                        <option value="box">box</option><option value="pkt">pkt</option>
                    </select>
                </div>
                <div><label class="block text-xs font-medium mb-1">Reorder level</label><input type="number" step="0.001" wire:model="itemReorder" class="w-full px-3 py-2 border rounded text-sm"></div>
                <div><label class="block text-xs font-medium mb-1">Max stock (optional)</label><input type="number" step="0.001" wire:model="itemMax" class="w-full px-3 py-2 border rounded text-sm"></div>
                <div class="flex items-center"><label class="flex items-center gap-2 mt-5"><input type="checkbox" wire:model="itemActive">Active</label></div>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" wire:click="cancelItemForm" class="px-4 py-2 text-sm text-slate-600">Cancel</button>
                <button type="button" wire:click="saveItem" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2 rounded text-sm font-semibold">{{ $editingItemId ? 'Update' : 'Save item' }}</button>
            </div>
        </div>
    @endif

    {{-- Vendor form --}}
    @if($showVendorForm)
        <div class="bg-white rounded-xl border p-6 mb-6 ring-2 ring-slate-300">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold">{{ $editingVendorId ? 'Edit vendor' : 'Add vendor' }}</h2>
                <button type="button" wire:click="cancelVendorForm" class="text-sm text-slate-500">Cancel</button>
            </div>
            <div class="grid md:grid-cols-3 gap-4 mb-4">
                <div><label class="block text-xs font-medium mb-1">Code *</label><input type="text" wire:model="vCode" class="w-full px-3 py-2 border rounded text-sm uppercase"></div>
                <div class="md:col-span-2"><label class="block text-xs font-medium mb-1">Vendor name *</label><input type="text" wire:model="vName" class="w-full px-3 py-2 border rounded text-sm"></div>
                <div><label class="block text-xs font-medium mb-1">Contact person</label><input type="text" wire:model="vContact" class="w-full px-3 py-2 border rounded text-sm"></div>
                <div><label class="block text-xs font-medium mb-1">Phone</label><input type="text" wire:model="vPhone" class="w-full px-3 py-2 border rounded text-sm"></div>
                <div><label class="block text-xs font-medium mb-1">Email</label><input type="email" wire:model="vEmail" class="w-full px-3 py-2 border rounded text-sm"></div>
                <div><label class="block text-xs font-medium mb-1">GST number</label><input type="text" wire:model="vGst" class="w-full px-3 py-2 border rounded text-sm"></div>
                <div><label class="block text-xs font-medium mb-1">Payment terms (days)</label><input type="number" wire:model="vTerms" class="w-full px-3 py-2 border rounded text-sm"></div>
                <div class="md:col-span-3"><label class="block text-xs font-medium mb-1">Address</label><textarea wire:model="vAddress" rows="2" class="w-full px-3 py-2 border rounded text-sm"></textarea></div>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" wire:click="cancelVendorForm" class="px-4 py-2 text-sm text-slate-600">Cancel</button>
                <button type="button" wire:click="saveVendor" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2 rounded text-sm font-semibold">{{ $editingVendorId ? 'Update' : 'Save vendor' }}</button>
            </div>
        </div>
    @endif

    {{-- Purchase form --}}
    @if($showPurchaseForm)
        <div class="bg-white rounded-xl border p-6 mb-6 ring-2 ring-emerald-200">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold">Record purchase / GRN</h2>
                <button type="button" wire:click="cancelPurchase" class="text-sm text-slate-500">Cancel</button>
            </div>
            <div class="grid md:grid-cols-2 gap-4 mb-4">
                <div><label class="block text-xs font-medium mb-1">Vendor *</label>
                    <select wire:model="purchaseVendorId" class="w-full px-3 py-2 border rounded text-sm">
                        <option value="">Select vendor…</option>
                        @foreach($vendors as $v)<option value="{{ $v->id }}">{{ $v->name }} ({{ $v->code }})</option>@endforeach
                    </select>
                </div>
                <div><label class="block text-xs font-medium mb-1">Vendor invoice #</label><input type="text" wire:model="purchaseInvoiceNumber" class="w-full px-3 py-2 border rounded text-sm" placeholder="INV-1234"></div>
            </div>
            <div class="font-semibold text-sm mb-2">Items received</div>
            <div class="space-y-2 mb-3">
                @foreach($purchaseLines as $i => $line)
                    <div class="flex items-center gap-2 bg-slate-50 rounded p-2">
                        <select wire:model="purchaseLines.{{ $i }}.item_id" class="flex-1 px-2 py-1.5 border rounded text-sm">
                            <option value="">Select item…</option>
                            @foreach($allActiveItems as $it)<option value="{{ $it->id }}">{{ $it->name }} ({{ $it->unit }})</option>@endforeach
                        </select>
                        <input type="number" step="0.001" wire:model="purchaseLines.{{ $i }}.qty" placeholder="Qty" class="w-24 px-2 py-1.5 border rounded text-sm">
                        <input type="number" step="0.01" wire:model="purchaseLines.{{ $i }}.price" placeholder="Unit ₹" class="w-28 px-2 py-1.5 border rounded text-sm">
                        <button type="button" wire:click="removePurchaseLine({{ $i }})" class="text-rose-600 text-sm">×</button>
                    </div>
                @endforeach
            </div>
            <button type="button" wire:click="addPurchaseLine" class="text-sm text-brand-600 hover:underline mb-4">+ Add another line</button>
            <div class="flex justify-end gap-3">
                <button type="button" wire:click="cancelPurchase" class="px-4 py-2 text-sm text-slate-600">Cancel</button>
                <button type="button" wire:click="recordPurchase" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2 rounded text-sm font-semibold">Record GRN & update stock</button>
            </div>
        </div>
    @endif

    {{-- Issue form --}}
    @if($showIssueForm)
        <div class="bg-white rounded-xl border p-6 mb-6 ring-2 ring-amber-200">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold">Issue / consume material</h2>
                <button type="button" wire:click="cancelIssue" class="text-sm text-slate-500">Cancel</button>
            </div>
            <div class="grid md:grid-cols-4 gap-4 mb-4">
                <div class="md:col-span-2"><label class="block text-xs font-medium mb-1">Item *</label>
                    <select wire:model="issueItemId" class="w-full px-3 py-2 border rounded text-sm">
                        <option value="">Select item…</option>
                        @foreach($allActiveItems as $it)<option value="{{ $it->id }}">{{ $it->name }} (in stock: {{ rtrim(rtrim($it->current_stock, '0'), '.') }} {{ $it->unit }})</option>@endforeach
                    </select>
                </div>
                <div><label class="block text-xs font-medium mb-1">Quantity *</label><input type="number" step="0.001" wire:model="issueQty" class="w-full px-3 py-2 border rounded text-sm"></div>
                <div><label class="block text-xs font-medium mb-1">Department</label>
                    <select wire:model="issueDept" class="w-full px-3 py-2 border rounded text-sm">
                        <option value="kitchen">Kitchen</option><option value="bar">Bar</option>
                        <option value="banquet">Banquet</option><option value="housekeeping">Housekeeping</option>
                        <option value="maintenance">Maintenance</option><option value="other">Other</option>
                    </select>
                </div>
                <div class="md:col-span-4"><label class="block text-xs font-medium mb-1">Notes</label><input type="text" wire:model="issueNotes" class="w-full px-3 py-2 border rounded text-sm"></div>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" wire:click="cancelIssue" class="px-4 py-2 text-sm text-slate-600">Cancel</button>
                <button type="button" wire:click="recordIssue" class="bg-amber-600 hover:bg-amber-700 text-white px-5 py-2 rounded text-sm font-semibold">Issue & deduct stock</button>
            </div>
        </div>
    @endif

    {{-- Adjustment --}}
    @if($showAdjustForm)
        <div class="bg-white rounded-xl border p-6 mb-6 ring-2 ring-violet-200">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold">Stock adjustment</h2>
                <button type="button" wire:click="cancelAdjust" class="text-sm text-slate-500">Cancel</button>
            </div>
            <div class="grid md:grid-cols-3 gap-4 mb-4">
                <div class="md:col-span-2"><label class="block text-xs font-medium mb-1">Item *</label>
                    <select wire:model="adjustItemId" class="w-full px-3 py-2 border rounded text-sm">
                        <option value="">Select item…</option>
                        @foreach($allActiveItems as $it)<option value="{{ $it->id }}">{{ $it->name }} (in stock: {{ rtrim(rtrim($it->current_stock, '0'), '.') }} {{ $it->unit }})</option>@endforeach
                    </select>
                </div>
                <div><label class="block text-xs font-medium mb-1">Qty (+/-) *</label><input type="number" step="0.001" wire:model="adjustQty" placeholder="-2.5" class="w-full px-3 py-2 border rounded text-sm"></div>
                <div class="md:col-span-3"><label class="block text-xs font-medium mb-1">Reason *</label><input type="text" wire:model="adjustReason" placeholder="Wastage / breakage / count correction" class="w-full px-3 py-2 border rounded text-sm"></div>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" wire:click="cancelAdjust" class="px-4 py-2 text-sm text-slate-600">Cancel</button>
                <button type="button" wire:click="recordAdjust" class="bg-violet-600 hover:bg-violet-700 text-white px-5 py-2 rounded text-sm font-semibold">Record adjustment</button>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-xl border overflow-hidden">
        <div class="border-b flex">
            @foreach(['items'=>'Items','vendors'=>'Vendors','pos'=>'Purchase orders','low'=>'Low stock','movements'=>'Stock movements'] as $k=>$l)
                <button type="button" wire:click="setTab('{{ $k }}')" class="px-5 py-3 text-sm font-medium border-b-2 transition {{ $tab === $k ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-600 hover:text-slate-900' }}">{{ $l }}</button>
            @endforeach
        </div>

        @if($tab === 'items')
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b"><tr><th class="px-5 py-2">Code</th><th class="px-4 py-2">Name</th><th class="px-4 py-2">Category</th><th class="px-4 py-2">UOM</th><th class="px-4 py-2 text-right">Stock</th><th class="px-4 py-2 text-right">Reorder lvl</th><th class="px-4 py-2 text-right">Last rate</th><th class="px-4 py-2 text-right">Actions</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($items as $i)
                        <tr><td class="px-5 py-2 font-mono text-xs">{{ $i->code }}</td><td class="px-4 py-2 font-medium">{{ $i->name }}</td><td class="px-4 py-2 text-xs">{{ $i->category?->name ?? '—' }}</td><td class="px-4 py-2 text-xs">{{ $i->unit }}</td><td class="px-4 py-2 text-right {{ $i->current_stock < $i->reorder_level ? 'text-rose-600 font-bold' : '' }}">{{ rtrim(rtrim($i->current_stock, '0'), '.') }}</td><td class="px-4 py-2 text-right">{{ rtrim(rtrim($i->reorder_level, '0'), '.') }}</td><td class="px-4 py-2 text-right">₹{{ number_format($i->last_purchase_price ?? 0, 2) }}</td><td class="px-4 py-2 text-right text-xs"><button type="button" wire:click="startEditItem({{ $i->id }})" class="text-brand-600 hover:underline">Edit</button> · <button type="button" wire:click="startIssue({{ $i->id }})" class="text-amber-700 hover:underline">Issue</button> · <button type="button" wire:click="startAdjust({{ $i->id }})" class="text-violet-700 hover:underline">Adjust</button></td></tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-8 text-center text-sm text-slate-500">No items configured. <button type="button" wire:click="startCreateItem" class="text-brand-600 hover:underline">+ Add item</button></td></tr>
                    @endforelse
                </tbody>
            </table>
        @elseif($tab === 'vendors')
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b"><tr><th class="px-5 py-2">Code</th><th class="px-4 py-2">Vendor</th><th class="px-4 py-2">Contact</th><th class="px-4 py-2">GST</th><th class="px-4 py-2">Terms</th><th class="px-4 py-2 text-right"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($vendors as $v)
                        <tr><td class="px-5 py-2 font-mono text-xs">{{ $v->code }}</td><td class="px-4 py-2 font-medium">{{ $v->name }}<div class="text-xs text-slate-500">{{ $v->contact_person }}</div></td><td class="px-4 py-2 text-xs">{{ $v->phone }}<div class="text-xs text-slate-500">{{ $v->email }}</div></td><td class="px-4 py-2 font-mono text-xs">{{ $v->gst_number }}</td><td class="px-4 py-2 text-xs">{{ $v->payment_terms_days }}d</td><td class="px-4 py-2 text-right text-xs"><button type="button" wire:click="startEditVendor({{ $v->id }})" class="text-brand-600 hover:underline">Edit</button></td></tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">No vendors yet. <button type="button" wire:click="startCreateVendor" class="text-brand-600 hover:underline">+ Add vendor</button></td></tr>
                    @endforelse
                </tbody>
            </table>
        @elseif($tab === 'pos')
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b"><tr><th class="px-5 py-2">PO #</th><th class="px-4 py-2">Vendor</th><th class="px-4 py-2">Date</th><th class="px-4 py-2 text-right">Total</th><th class="px-4 py-2">Status</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($purchaseOrders as $po)
                        <tr><td class="px-5 py-2 font-mono text-xs">{{ $po->po_number }}</td><td class="px-4 py-2">{{ $po->vendor?->name ?? '—' }}</td><td class="px-4 py-2 text-xs">{{ $po->po_date?->format('d M Y') }}</td><td class="px-4 py-2 text-right font-medium">₹{{ number_format($po->total_amount, 2) }}</td><td class="px-4 py-2"><span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded bg-slate-100">{{ $po->status }}</span></td></tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500">No purchase orders. <button type="button" wire:click="startPurchase" class="text-brand-600 hover:underline">+ Record purchase</button></td></tr>
                    @endforelse
                </tbody>
            </table>
        @elseif($tab === 'low')
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b"><tr><th class="px-5 py-2">Code</th><th class="px-4 py-2">Item</th><th class="px-4 py-2 text-right">Current</th><th class="px-4 py-2 text-right">Reorder lvl</th><th class="px-4 py-2 text-right">Suggested order</th><th class="px-4 py-2 text-right"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($lowStock as $i)
                        <tr class="bg-rose-50/50"><td class="px-5 py-2 font-mono text-xs">{{ $i->code }}</td><td class="px-4 py-2 font-medium">{{ $i->name }}</td><td class="px-4 py-2 text-right text-rose-600 font-bold">{{ rtrim(rtrim($i->current_stock, '0'), '.') }} {{ $i->unit }}</td><td class="px-4 py-2 text-right">{{ rtrim(rtrim($i->reorder_level, '0'), '.') }}</td><td class="px-4 py-2 text-right">{{ rtrim(rtrim(max(0, $i->reorder_level - $i->current_stock), '0'), '.') }} {{ $i->unit }}</td><td class="px-4 py-2 text-right"><button type="button" wire:click="startPurchase" class="text-xs text-brand-600 hover:underline">Order</button></td></tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">All items above reorder level. ✓</td></tr>
                    @endforelse
                </tbody>
            </table>
        @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 border-b"><tr><th class="px-5 py-2">Date</th><th class="px-4 py-2">Item</th><th class="px-4 py-2">Type</th><th class="px-4 py-2 text-right">Qty</th><th class="px-4 py-2 text-right">Cost</th><th class="px-4 py-2">Notes</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($movements as $m)
                        <tr>
                            <td class="px-5 py-2 text-xs">{{ $m->created_at->format('d M H:i') }}</td>
                            <td class="px-4 py-2">{{ $m->item?->name ?? '—' }}</td>
                            <td class="px-4 py-2">
                                @php $cls = ['receipt'=>'bg-emerald-100 text-emerald-700','issue'=>'bg-amber-100 text-amber-700','adjustment'=>'bg-violet-100 text-violet-700','wastage'=>'bg-rose-100 text-rose-700'][$m->movement_type] ?? 'bg-slate-100'; @endphp
                                <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded {{ $cls }}">{{ $m->movement_type }}</span>
                            </td>
                            <td class="px-4 py-2 text-right font-bold {{ $m->quantity >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $m->quantity >= 0 ? '+' : '' }}{{ rtrim(rtrim($m->quantity, '0'), '.') }} {{ $m->item?->unit }}</td>
                            <td class="px-4 py-2 text-right">₹{{ number_format($m->total_cost, 2) }}</td>
                            <td class="px-4 py-2 text-xs text-slate-600">{{ $m->notes }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">No stock movements yet. <button type="button" wire:click="startPurchase" class="text-brand-600 hover:underline">Record first purchase</button></td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>
</div>
