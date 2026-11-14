@extends('layouts.app')

@section('title', 'Access Rights')
@section('breadcrumb', 'User Management / Sub-Admins / '.$user->name.' / Access Rights')

@php
    $columns = [
        'view' => ['View', 'See records and lists'],
        'create' => ['Create', 'Add new records'],
        'edit' => ['Edit', 'Change existing records'],
        'delete' => ['Delete', 'Remove records'],
    ];
@endphp

@section('content')
    <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-navy-800 text-sm font-bold text-white">{{ $user->initials() }}</span>
            <div>
                <div class="font-bold text-navy-900">{{ $user->name }}</div>
                <div class="text-sm text-slate-500">
                    <span class="badge bg-brand-50 text-brand-700">{{ $user->roleLabel() }}</span>
                    <span class="ml-1">{{ $user->email }}</span>
                    @unless ($user->is_active) <span class="badge ml-1 bg-slate-100 text-slate-600">Inactive</span> @endunless
                </div>
            </div>
        </div>

        @if ($otherUsers->isNotEmpty())
            <form method="GET" class="flex items-center gap-2">
                <label for="copy_from" class="text-sm font-semibold whitespace-nowrap text-slate-600">Copy rights from</label>
                <select id="copy_from" name="copy_from" class="form-input w-auto min-w-[200px]">
                    <option value="">— Select sub-admin —</option>
                    @foreach ($otherUsers as $other)
                        <option value="{{ $other->id }}" @selected($copiedFrom?->is($other))>{{ $other->name }} ({{ $other->roleLabel() }})</option>
                    @endforeach
                </select>
                <button class="btn btn-secondary"><x-icon name="copy" class="h-4 w-4" /> Load</button>
            </form>
        @endif
    </div>

    @if ($copiedFrom)
        <div class="mb-5 rounded border border-amber-200 border-l-4 border-l-amber-500 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Showing rights copied from <strong>{{ $copiedFrom->name }}</strong>. Review them, then click <strong>Save access rights</strong> to apply them to {{ $user->name }}.
        </div>
    @endif

    <form method="POST" action="{{ route('admin.users.permissions.update', $user) }}" id="permission-form">
        @csrf @method('PUT')
        <div class="card">
            <div class="card-header flex-wrap">
                <div>
                    <h2 class="card-title">Module access</h2>
                    <p class="text-xs text-slate-500">Turn a module off to hide it completely from this user; turning it on grants View, Create and Edit. Delete is always granted separately. Ticking Create, Edit or Delete also grants View.</p>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bulk="all">Grant everything</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bulk="none">Revoke everything</button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead>
                    <tr>
                        <th class="min-w-[220px]">Module</th>
                        <th class="text-center">Show module</th>
                        @foreach ($columns as $action => [$label, $hint])
                            <th class="text-center" title="{{ $hint }}">
                                <label class="inline-flex cursor-pointer flex-col items-center gap-1">
                                    {{ $label }}
                                    <input type="checkbox" class="h-3.5 w-3.5 accent-brand-600" data-col="{{ $action }}" title="Select column">
                                </label>
                            </th>
                        @endforeach
                        <th class="text-center">Full access</th>
                    </tr>
                    </thead>
                    <tbody>
                    @php $lastGroup = null; @endphp
                    @foreach ($modules as $key => $mod)
                        @php $p = $granted[$key] ?? null; $group = $mod['group'] ?? ''; @endphp
                        @if ($group !== $lastGroup)
                            <tr class="pointer-events-none">
                                <td colspan="7" class="!bg-navy-900/[0.04] !py-1.5 text-[11px] font-bold tracking-widest text-navy-700 uppercase">
                                    {{ \App\Support\Modules::GROUPS[$group]['label'] ?? 'Modules' }}
                                </td>
                            </tr>
                            @php $lastGroup = $group; @endphp
                        @endif
                        <tr data-row="{{ $key }}">
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded bg-navy-900 text-gold-500"><x-icon :name="$mod['icon']" class="h-4 w-4" /></span>
                                    <div>
                                        <div class="font-semibold text-slate-800">{{ $mod['label'] }}</div>
                                        <div class="text-xs text-slate-500">{{ $mod['description'] }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                <label class="inline-flex cursor-pointer items-center">
                                    <input type="checkbox" name="permissions[{{ $key }}][show]" value="1" class="peer sr-only" data-action="show" @checked($p?->can_show)>
                                    <span class="relative h-5 w-9 rounded-full bg-slate-300 transition peer-checked:bg-emerald-600 after:absolute after:top-0.5 after:left-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-4"></span>
                                </label>
                            </td>
                            @foreach ($columns as $action => [$label, $hint])
                                <td class="text-center">
                                    <input type="checkbox" name="permissions[{{ $key }}][{{ $action }}]" value="1" data-action="{{ $action }}"
                                           class="h-4 w-4 cursor-pointer accent-brand-600 disabled:cursor-not-allowed disabled:opacity-30"
                                           aria-label="{{ $label }} {{ $mod['label'] }}" @checked($p?->{'can_'.$action})>
                                </td>
                            @endforeach
                            <td class="text-center">
                                <input type="checkbox" class="h-4 w-4 cursor-pointer accent-navy-800" data-full aria-label="Full access {{ $mod['label'] }}">
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50/60 px-5 py-3">
                <p class="text-sm text-slate-500"><span id="summary" class="font-semibold text-slate-700"></span></p>
                <div class="flex gap-2">
                    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Back to list</a>
                    <button class="btn btn-primary"><x-icon name="check" class="h-4 w-4" /> Save access rights</button>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    (function () {
        const rows = [...document.querySelectorAll('tr[data-row]')];
        const crud = ['view', 'create', 'edit', 'delete'];
        const box = (row, action) => row.querySelector(`[data-action="${action}"]`);

        // Keep a row consistent: no rights without "show", no create/edit/delete without "view".
        function normalize(row, changed) {
            const show = box(row, 'show');
            // Switching a module on grants the default rights (all but Delete), matching new accounts.
            if (changed === 'show' && show.checked) {
                ['view', 'create', 'edit'].forEach((a) => box(row, a).checked = true);
            }
            if (changed && changed !== 'show' && box(row, changed).checked) {
                show.checked = true;
                if (changed !== 'view') box(row, 'view').checked = true;
            }
            if (changed === 'view' && !box(row, 'view').checked) {
                ['create', 'edit', 'delete'].forEach((a) => box(row, a).checked = false);
            }
            crud.forEach((a) => {
                box(row, a).disabled = !show.checked;
                if (!show.checked) box(row, a).checked = false;
            });
            row.querySelector('[data-full]').checked = show.checked && crud.every((a) => box(row, a).checked);
            row.classList.toggle('opacity-60', !show.checked);
        }

        function refreshHeader() {
            crud.forEach((a) => {
                document.querySelector(`[data-col="${a}"]`).checked = rows.every((r) => box(r, a).checked);
            });
            const shown = rows.filter((r) => box(r, 'show').checked).length;
            document.getElementById('summary').textContent = `${shown} of ${rows.length} modules visible to this user`;
        }

        rows.forEach((row) => {
            ['show', ...crud].forEach((a) => box(row, a).addEventListener('change', () => {
                if (a === 'show') crud.forEach((c) => box(row, c).disabled = false);
                normalize(row, a);
                refreshHeader();
            }));
            row.querySelector('[data-full]').addEventListener('change', (e) => {
                ['show', ...crud].forEach((a) => box(row, a).checked = e.target.checked);
                normalize(row);
                refreshHeader();
            });
            normalize(row);
        });

        document.querySelectorAll('[data-col]').forEach((col) => {
            col.addEventListener('change', () => {
                rows.forEach((row) => { box(row, col.dataset.col).checked = col.checked; normalize(row, col.dataset.col); });
                refreshHeader();
            });
        });

        document.querySelectorAll('[data-bulk]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const on = btn.dataset.bulk === 'all';
                rows.forEach((row) => { ['show', ...crud].forEach((a) => box(row, a).checked = on); normalize(row); });
                refreshHeader();
            });
        });

        refreshHeader();
    })();
</script>
@endpush
