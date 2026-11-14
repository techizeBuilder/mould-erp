@extends('layouts.app')

@section('title', $customer->company_name)
@section('breadcrumb', 'Sales & CRM / Customer Retention / '.$customer->code)

@use('App\Models\Product')
@use('App\Support\Money')
@use('App\Support\RetentionHealth')

@php $r = $customer->retention(); @endphp

@section('content')
    <div class="max-w-5xl space-y-6">
        <div class="card">
            <div class="card-header flex-wrap">
                <div>
                    <div class="font-mono text-xs font-semibold text-navy-700">{{ $customer->code }}</div>
                    <h2 class="card-title">{{ $customer->company_name }}
                        <span class="badge ml-1 {{ RetentionHealth::tone($r['health']) }}">{{ RetentionHealth::label($r['health']) }}</span>
                        @if ($customer->status !== 'active')<span class="badge {{ $customer->statusTone() }}">{{ $customer->statusLabel() }}</span>@endif
                    </h2>
                </div>
                <div class="flex flex-wrap gap-2">
                    @module('sales_quotations', 'create')
                        <a href="{{ route('sales.quotations.create', ['customer' => $customer->id]) }}" class="btn btn-primary"><x-icon name="document" class="h-4 w-4" /> New quotation</a>
                    @endmodule
                    @module('sales_retention', 'edit')
                        <a href="{{ route('sales.retention.edit', $customer) }}" class="btn btn-secondary"><x-icon name="pencil" class="h-4 w-4" /> Edit</a>
                    @endmodule
                    @module('sales_retention', 'delete')
                        <form method="POST" action="{{ route('sales.retention.destroy', $customer) }}" data-confirm="Delete customer &quot;{{ $customer->company_name }}&quot;?">
                            @csrf @method('DELETE')
                            <button class="btn border border-red-200 bg-white text-red-600 hover:bg-red-50"><x-icon name="trash" class="h-4 w-4" /> Delete</button>
                        </form>
                    @endmodule
                </div>
            </div>
            <dl class="grid gap-5 p-5 sm:grid-cols-2 lg:grid-cols-4">
                <x-detail label="Contact person">{{ $customer->contact_person }}</x-detail>
                <x-detail label="Mobile">{{ $customer->phone }}</x-detail>
                <x-detail label="Email">{{ $customer->email }}</x-detail>
                <x-detail label="City / state">{{ collect([$customer->city, $customer->state])->filter()->join(', ') }}</x-detail>
                <x-detail label="Industry">{{ $customer->industryLabel() }}</x-detail>
                <x-detail label="Buyer type">{{ $customer->buyerTypeLabel() }}</x-detail>
                <x-detail label="Payment terms">{{ $customer->paymentTermsLabel() }}</x-detail>
                <x-detail label="How they came to us">{{ $customer->sourceLabel() }}</x-detail>
                <x-detail label="GSTIN">{{ $customer->gstin }}</x-detail>
                <x-detail label="Account owner">{{ $customer->assignee?->name }}</x-detail>
                <x-detail label="Last order">{{ $r['last_order'] ? $r['last_order']->format('d M Y').' ('.$r['days_since'].' d ago)' : '' }}</x-detail>
                <x-detail label="Next order expected">{{ $r['next_expected']?->format('d M Y') }}</x-detail>
                <x-detail label="Reorder cycle">
                    {{ $r['cycle'] }} days
                    <span class="text-xs text-slate-500">({{ $customer->reorder_cycle_days ? 'set by hand' : ($r['average_gap'] ? 'their average' : 'default') }})</span>
                </x-detail>
                <x-detail label="Total orders">{{ $customer->orderCount() }}</x-detail>
                <x-detail label="Total order value">₹{{ Money::format($customer->lifetimeValue()) }}</x-detail>
                @if ($customer->remarks)<x-detail label="Remarks" class="sm:col-span-2 lg:col-span-4"><span class="whitespace-pre-line">{{ $customer->remarks }}</span></x-detail>@endif
            </dl>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Products they buy</h2></div>
            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead><tr><th>Product</th><th class="text-right">Orders</th><th class="text-right">Total qty</th><th class="text-right">Last rate</th><th>Last order</th><th>Next expected</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($history as $row)
                        <tr>
                            <td class="font-semibold text-slate-800">{{ $row['description'] }}</td>
                            <td class="text-right tabular-nums">{{ $row['orders'] }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ Money::format($row['quantity'], 0) }} <span class="text-xs text-slate-500">{{ $row['unit'] }}</span></td>
                            <td class="text-right tabular-nums whitespace-nowrap">₹{{ Money::format($row['last_rate'], 3) }}</td>
                            <td class="whitespace-nowrap">{{ $row['last_order']?->format('d M Y') }}</td>
                            <td class="whitespace-nowrap">{{ $row['next_expected']?->format('d M Y') }}</td>
                            <td><span class="badge {{ RetentionHealth::tone($row['health']) }}">{{ RetentionHealth::label($row['health']) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-sm text-slate-500">No orders yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @module('orders_sales', 'create')
            <p class="rounded border border-sky-200 bg-sky-50 px-4 py-2.5 text-sm text-sky-800">New orders go in through <a href="{{ route('orders.sales.create', ['customer' => $customer->id]) }}" class="font-semibold underline">Sales Orders</a>, which adds them here once approved. Use the form below only for older orders placed before that.</p>
        @endmodule

        @module('sales_retention', 'create')
            <details class="card" @if ($errors->any()) open @endif>
                <summary class="card-header cursor-pointer"><span class="card-title">Record an older order</span></summary>
                <form method="POST" action="{{ route('sales.retention.orders.store', $customer) }}" class="grid gap-4 p-5 md:grid-cols-4">
                    @csrf
                    <div class="md:col-span-2">
                        <label for="product_id" class="form-label">Product</label>
                        <select id="product_id" name="product_id" class="form-input">
                            <option value="">— Not in Product Master —</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" @selected((int) old('product_id') === $product->id)>{{ $product->code }} – {{ $product->name }}</option>
                            @endforeach
                        </select>
                        @error('product_id') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <x-form.input class="md:col-span-2" name="description" label="Description (if not in master)" maxlength="255" />
                    <x-form.input name="order_date" label="Order date" type="date" :value="now()->format('Y-m-d')" required />
                    <x-form.input name="po_number" label="Buyer's PO no." maxlength="60" />
                    <x-form.input name="quantity" label="Quantity" type="number" min="1" step="1" required />
                    <x-form.select name="unit" label="Unit" :options="Product::UNITS" value="pcs" required />
                    <x-form.input name="rate" label="Rate (₹ / unit)" type="number" min="0" step="0.001" required />
                    <x-form.input class="md:col-span-2" name="remarks" label="Remarks" maxlength="500" />
                    <div class="flex items-end"><button class="btn btn-primary">Record order</button></div>
                </form>
            </details>
        @endmodule

        <div class="card">
            <div class="card-header"><h2 class="card-title">Order history</h2></div>
            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead><tr><th>Date</th><th>Product</th><th>PO / quotation</th><th class="text-right">Qty</th><th class="text-right">Rate</th><th class="text-right">Amount</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td class="whitespace-nowrap">{{ $order->order_date->format('d M Y') }}</td>
                            <td>{{ $order->description }}</td>
                            <td class="whitespace-nowrap">
                                {{ $order->po_number ?: '—' }}
                                @if ($order->quotation)
                                    @module('sales_quotations', 'view')
                                        <div><a href="{{ route('sales.quotations.show', $order->quotation) }}" class="font-mono text-xs font-semibold text-brand-600 hover:underline">{{ $order->quotation->number() }}</a></div>
                                    @endmodule
                                @endif
                            </td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ Money::format($order->quantity, 0) }} <span class="text-xs text-slate-500">{{ $order->unitLabel() }}</span></td>
                            <td class="text-right tabular-nums whitespace-nowrap">₹{{ Money::format($order->rate, 3) }}</td>
                            <td class="text-right font-semibold tabular-nums whitespace-nowrap">₹{{ Money::format($order->amount) }}</td>
                            <td class="text-right">
                                @module('sales_retention', 'delete')
                                    <form method="POST" action="{{ route('sales.retention.orders.destroy', [$customer, $order]) }}" data-confirm="Remove this order from the history?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-secondary btn-sm text-red-600" title="Remove" aria-label="Remove order"><x-icon name="trash" class="h-4 w-4" /></button>
                                    </form>
                                @endmodule
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-sm text-slate-500">No orders yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            @module('sales_quotations', 'view')
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Quotations</h2></div>
                    <ul class="divide-y divide-slate-100">
                        @forelse ($quotations as $q)
                            <li class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
                                <a href="{{ route('sales.quotations.show', $q) }}" class="font-mono text-xs font-semibold text-brand-600 hover:underline">{{ $q->number() }}</a>
                                <span class="tabular-nums">₹{{ Money::format($q->total, 0) }}</span>
                                <span class="badge {{ $q->statusTone() }}">{{ $q->statusLabel() }}</span>
                            </li>
                        @empty
                            <li class="px-5 py-4 text-sm text-slate-500">No quotations yet.</li>
                        @endforelse
                    </ul>
                </div>
            @endmodule

            @module('sales_leads', 'view')
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Enquiries</h2></div>
                    <ul class="divide-y divide-slate-100">
                        @forelse ($leads as $lead)
                            <li class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
                                <a href="{{ route('sales.leads.show', $lead) }}" class="font-mono text-xs font-semibold text-brand-600 hover:underline">{{ $lead->code }}</a>
                                <span>{{ $lead->sourceLabel() }}</span>
                                <span class="badge {{ $lead->statusTone() }}">{{ $lead->statusLabel() }}</span>
                            </li>
                        @empty
                            <li class="px-5 py-4 text-sm text-slate-500">No enquiries on record.</li>
                        @endforelse
                    </ul>
                </div>
            @endmodule
        </div>

        @module('sales_followups', 'show')
            @include('sales._followup-list', ['createQuery' => ['customer' => $customer->id]])
        @endmodule

        <a href="{{ route('sales.retention.index') }}" class="inline-block text-sm font-semibold text-brand-600 hover:underline">← Back to customers</a>
    </div>
@endsection
