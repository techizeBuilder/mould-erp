@extends('layouts.app')

@section('title', 'Customer Retention')
@section('breadcrumb', 'Sales & CRM / Customer Retention')

@use('App\Models\Lead')
@use('App\Support\Money')
@use('App\Support\RetentionHealth')

@section('content')
    @module('sales_retention', 'view')
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            @include('sales._stat', ['label' => 'Healthy', 'value' => $byHealth['healthy'] ?? 0, 'tone' => 'text-emerald-700', 'href' => route('sales.retention.index', ['health' => 'healthy'])])
            @include('sales._stat', ['label' => 'Reorder due', 'value' => $byHealth['due'] ?? 0, 'tone' => 'text-sky-700', 'href' => route('sales.retention.index', ['health' => 'due'])])
            @include('sales._stat', ['label' => 'At risk', 'value' => $byHealth['at_risk'] ?? 0, 'tone' => 'text-amber-700', 'href' => route('sales.retention.index', ['health' => 'at_risk'])])
            @include('sales._stat', ['label' => 'Lapsed', 'value' => $byHealth['lost'] ?? 0, 'tone' => 'text-red-700', 'href' => route('sales.retention.index', ['health' => 'lost'])])
            @include('sales._stat', ['label' => 'Repeat customers', 'value' => $stats['repeat_rate'] !== null ? $stats['repeat_rate'].'%' : '—', 'hint' => 'Ordered more than once'])
            @include('sales._stat', ['label' => 'Total order value', 'value' => '₹'.Money::format($stats['value'], 0)])
        </div>
    @endmodule

    <div class="card">
        <div class="card-header flex-wrap">
            <h2 class="card-title">Customers @module('sales_retention', 'view')<span class="ml-1 text-sm font-normal text-slate-500">({{ $customers->total() }})</span>@endmodule</h2>
            <div class="flex flex-wrap gap-2">
                @module('sales_followups', 'create')
                    <form method="POST" action="{{ route('sales.retention.reminders') }}" data-confirm="Schedule a reorder call today for every customer who is due or at risk and has none pending?">
                        @csrf
                        <button class="btn btn-secondary"><x-icon name="clock" class="h-4 w-4" /> Schedule reorder reminders</button>
                    </form>
                @endmodule
                @module('sales_retention', 'create')
                    <a href="{{ route('sales.retention.create') }}" class="btn btn-primary"><x-icon name="user-plus" class="h-4 w-4" /> Add Customer</a>
                @endmodule
            </div>
        </div>

        @module('sales_retention', 'view')
            <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-200 bg-slate-50/60 px-5 py-3">
                <div class="min-w-[200px] flex-1">
                    <div class="relative">
                        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input name="search" value="{{ request('search') }}" class="form-input pl-9" placeholder="Search code, company, contact, mobile or city">
                    </div>
                </div>
                <select name="health" class="form-input w-auto" aria-label="Health">
                    <option value="">All health</option>
                    @foreach (RetentionHealth::STATES as $value => $state)
                        <option value="{{ $value }}" @selected(request('health') === $value)>{{ $state['label'] }}</option>
                    @endforeach
                </select>
                <select name="industry" class="form-input w-auto" aria-label="Industry">
                    <option value="">All industries</option>
                    @foreach (Lead::INDUSTRIES as $value => $label)
                        <option value="{{ $value }}" @selected(request('industry') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="product_id" class="form-input w-auto max-w-56" aria-label="Buys product">
                    <option value="">Buying any product</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected((int) request('product_id') === $p->id)>{{ $p->code }} – {{ $p->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="form-input w-auto" aria-label="Status">
                    <option value="active" @selected(request('status', 'active') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                </select>
                <button class="btn btn-secondary">Filter</button>
                @if (request()->hasAny(['search', 'health', 'industry', 'product_id', 'status']))
                    <a href="{{ route('sales.retention.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-700">Reset</a>
                @endif
            </form>

            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Last order</th>
                        <th>Next expected</th>
                        <th class="text-right">Orders</th>
                        <th class="text-right">Order value</th>
                        <th>Next follow-up</th>
                        <th>Health</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($customers as $customer)
                        @php $r = $customer->retention(); $next = $customer->followUps->first(); @endphp
                        <tr>
                            <td>
                                <a href="{{ route('sales.retention.show', $customer) }}" class="font-semibold text-slate-800 hover:text-brand-600 hover:underline">{{ $customer->company_name }}</a>
                                <div class="text-xs text-slate-500">{{ collect([$customer->code, $customer->contact_person, $customer->phone])->filter()->join(' · ') }}</div>
                            </td>
                            <td class="whitespace-nowrap">
                                @if ($r['last_order'])
                                    {{ $r['last_order']->format('d M Y') }}
                                    <div class="text-xs text-slate-500">{{ $r['days_since'] }} d ago</div>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap">
                                {{ $r['next_expected']?->format('d M Y') ?? '—' }}
                                @if ($r['last_order'])<div class="text-xs text-slate-500">Every {{ $r['cycle'] }} d</div>@endif
                            </td>
                            <td class="text-right tabular-nums">{{ $customer->orderCount() }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap">₹{{ Money::format($customer->lifetimeValue(), 0) }}</td>
                            <td class="whitespace-nowrap">
                                @if ($next)
                                    <span class="{{ $next->isOverdue() ? 'font-semibold text-red-700' : '' }}">{{ $next->due_at->format('d M') }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td><span class="badge {{ RetentionHealth::tone($r['health']) }}">{{ RetentionHealth::label($r['health']) }}</span></td>
                            <td>
                                <div class="flex justify-end gap-1.5">
                                    <a href="{{ route('sales.retention.show', $customer) }}" class="btn btn-secondary btn-sm" title="View"><x-icon name="eye" class="h-4 w-4" /></a>
                                    @module('sales_followups', 'create')
                                        <a href="{{ route('sales.followups.create', ['customer' => $customer->id, 'purpose' => 'reorder']) }}" class="btn btn-secondary btn-sm" title="Schedule follow-up"><x-icon name="clock" class="h-4 w-4" /></a>
                                    @endmodule
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-sm text-slate-500">
                                No customers found. Customers are added automatically when a quotation is won.
                                @module('sales_retention', 'create')<a href="{{ route('sales.retention.create') }}" class="font-semibold text-brand-600 hover:underline">Add an existing customer</a>.@endmodule
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($customers->hasPages())<div class="border-t border-slate-200 px-5 py-3">{{ $customers->links() }}</div>@endif
        @else
            <p class="px-5 py-10 text-center text-sm text-slate-500">You don't have permission to view these records.</p>
        @endmodule
    </div>
@endsection
