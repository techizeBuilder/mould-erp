@extends('layouts.app')

@section('title', 'Follow-ups')
@section('breadcrumb', 'Sales & CRM / Follow-ups')

@use('App\Models\FollowUp')

@section('content')
    @module('sales_followups', 'view')
        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            @include('sales._stat', ['label' => 'Overdue', 'value' => $counts['overdue'], 'tone' => 'text-red-700', 'href' => route('sales.followups.index', ['tab' => 'overdue'])])
            @include('sales._stat', ['label' => 'Due today', 'value' => $counts['today'], 'tone' => 'text-amber-700', 'href' => route('sales.followups.index', ['tab' => 'today'])])
            @include('sales._stat', ['label' => 'Upcoming', 'value' => $counts['upcoming'], 'href' => route('sales.followups.index', ['tab' => 'upcoming'])])
        </div>
    @endmodule

    <div class="card">
        <div class="card-header flex-wrap">
            <h2 class="card-title">Follow-ups @module('sales_followups', 'view')<span class="ml-1 text-sm font-normal text-slate-500">({{ $followUps->total() }})</span>@endmodule</h2>
            @module('sales_followups', 'create')
                <a href="{{ route('sales.followups.create') }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> Schedule Follow-up</a>
            @endmodule
        </div>

        @module('sales_followups', 'view')
            <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-200 bg-slate-50/60 px-5 py-3">
                <select name="tab" class="form-input w-auto" aria-label="Show">
                    @foreach (['pending' => 'All pending', 'overdue' => 'Overdue', 'today' => 'Due today', 'upcoming' => 'Upcoming', 'done' => 'Done', 'all' => 'Everything'] as $value => $label)
                        <option value="{{ $value }}" @selected($tab === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="purpose" class="form-input w-auto" aria-label="Purpose">
                    <option value="">All purposes</option>
                    @foreach (FollowUp::PURPOSES as $value => $label)
                        <option value="{{ $value }}" @selected(request('purpose') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="type" class="form-input w-auto" aria-label="Type">
                    <option value="">All types</option>
                    @foreach (FollowUp::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="product_id" class="form-input w-auto max-w-56" aria-label="Product">
                    <option value="">All products</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected((int) request('product_id') === $p->id)>{{ $p->code }} – {{ $p->name }}</option>
                    @endforeach
                </select>
                <label class="flex items-center gap-2 py-2 text-sm"><input type="checkbox" name="assigned" value="me" class="h-4 w-4 accent-brand-600" @checked(request('assigned') === 'me')> Assigned to me</label>
                <button class="btn btn-secondary">Filter</button>
                @if (request()->hasAny(['tab', 'purpose', 'type', 'product_id', 'assigned']))
                    <a href="{{ route('sales.followups.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-700">Reset</a>
                @endif
            </form>

            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead>
                    <tr>
                        <th>When</th>
                        <th>Buyer</th>
                        <th>Purpose</th>
                        <th>Regarding</th>
                        <th>Assigned</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($followUps as $f)
                        @php $subject = $f->subject(); @endphp
                        <tr>
                            <td class="whitespace-nowrap {{ $f->isOverdue() ? 'font-semibold text-red-700' : '' }}">
                                {{ $f->due_at->format('d M Y') }}
                                <div class="text-xs font-normal text-slate-500">{{ $f->due_at->format('h:i A') }}</div>
                            </td>
                            <td>
                                <div class="font-semibold text-slate-800">{{ $f->buyerName() }}</div>
                                <div class="text-xs text-slate-500">{{ $f->buyerPhone() }}</div>
                            </td>
                            <td>
                                {{ $f->purposeLabel() }}
                                <div class="text-xs text-slate-500">{{ $f->typeLabel() }}</div>
                            </td>
                            <td class="max-w-xs">
                                @if ($subject)<a href="{{ route($subject[1], $subject[2]) }}" class="font-mono text-xs font-semibold text-brand-600 hover:underline">{{ $subject[0] }}</a>@endif
                                @if ($f->product)<div class="truncate text-xs text-slate-500" title="{{ $f->product->name }}">{{ $f->product->name }}</div>@endif
                            </td>
                            <td class="whitespace-nowrap">{{ $f->assignee?->name ?? '—' }}</td>
                            <td><span class="badge {{ $f->statusTone() }}">{{ $f->statusLabel() }}</span></td>
                            <td>
                                <div class="flex justify-end gap-1.5">
                                    <a href="{{ route('sales.followups.show', $f) }}" class="btn btn-secondary btn-sm" title="{{ $f->isPending() ? 'Open / complete' : 'View' }}"><x-icon name="{{ $f->isPending() ? 'check' : 'eye' }}" class="h-4 w-4" /></a>
                                    @if ($f->isPending())
                                        @module('sales_followups', 'edit')
                                            <a href="{{ route('sales.followups.edit', $f) }}" class="btn btn-secondary btn-sm" title="Edit"><x-icon name="pencil" class="h-4 w-4" /></a>
                                        @endmodule
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-12 text-center text-sm text-slate-500">Nothing here.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($followUps->hasPages())<div class="border-t border-slate-200 px-5 py-3">{{ $followUps->links() }}</div>@endif
        @else
            <p class="px-5 py-10 text-center text-sm text-slate-500">You don't have permission to view these records.</p>
        @endmodule
    </div>
@endsection
