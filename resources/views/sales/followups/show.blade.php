@extends('layouts.app')

@section('title', 'Follow-up')
@section('breadcrumb', 'Sales & CRM / Follow-ups / '.$followUp->buyerName())

@use('App\Models\FollowUp')

@php $subject = $followUp->subject(); @endphp

@section('content')
    <div class="max-w-3xl space-y-6">
        <div class="card">
            <div class="card-header flex-wrap">
                <h2 class="card-title">{{ $followUp->purposeLabel() }} — {{ $followUp->buyerName() }} <span class="badge ml-1 {{ $followUp->statusTone() }}">{{ $followUp->statusLabel() }}</span></h2>
                <div class="flex flex-wrap gap-2">
                    @if ($followUp->isPending())
                        @module('sales_followups', 'edit')
                            <a href="{{ route('sales.followups.edit', $followUp) }}" class="btn btn-secondary btn-sm"><x-icon name="pencil" class="h-4 w-4" /> Edit</a>
                            <form method="POST" action="{{ route('sales.followups.cancel', $followUp) }}" data-confirm="Cancel this follow-up?">
                                @csrf
                                <button class="btn btn-secondary btn-sm"><x-icon name="x" class="h-4 w-4" /> Cancel it</button>
                            </form>
                        @endmodule
                    @endif
                    @module('sales_followups', 'delete')
                        <form method="POST" action="{{ route('sales.followups.destroy', $followUp) }}" data-confirm="Delete this follow-up?">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm border border-red-200 bg-white text-red-600 hover:bg-red-50"><x-icon name="trash" class="h-4 w-4" /> Delete</button>
                        </form>
                    @endmodule
                </div>
            </div>
            <dl class="grid gap-5 p-5 sm:grid-cols-2">
                <x-detail label="Date & time">{{ $followUp->due_at->format('d M Y, h:i A') }}</x-detail>
                <x-detail label="How">{{ $followUp->typeLabel() }}</x-detail>
                <x-detail label="Buyer's mobile">{{ $followUp->buyerPhone() }}</x-detail>
                <x-detail label="Assigned to">{{ $followUp->assignee?->name }}</x-detail>
                <x-detail label="On">
                    @if ($subject)<a href="{{ route($subject[1], $subject[2]) }}" class="font-semibold text-brand-600 hover:underline">{{ $subject[0] }}</a>@endif
                </x-detail>
                <x-detail label="Product">{{ $followUp->product?->name }}</x-detail>
                @if ($followUp->agenda)<x-detail label="Agenda" class="sm:col-span-2"><span class="whitespace-pre-line">{{ $followUp->agenda }}</span></x-detail>@endif
                @if ($followUp->outcome)
                    <x-detail label="Outcome" class="sm:col-span-2"><span class="whitespace-pre-line">{{ $followUp->outcome }}</span></x-detail>
                    <x-detail label="Completed">{{ $followUp->completed_at?->format('d M Y, h:i A') }}{{ $followUp->completer ? ' · '.$followUp->completer->name : '' }}</x-detail>
                @endif
            </dl>
        </div>

        @if ($followUp->isPending())
            @module('sales_followups', 'edit')
                <form method="POST" action="{{ route('sales.followups.complete', $followUp) }}" class="card">
                    @csrf
                    <div class="card-header"><h2 class="card-title">Complete this follow-up</h2></div>
                    <div class="grid gap-5 p-5 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label for="outcome" class="form-label">What happened? <span class="text-red-600">*</span></label>
                            <textarea id="outcome" name="outcome" rows="3" required maxlength="1000" class="form-input">{{ old('outcome') }}</textarea>
                            @error('outcome') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        @module('sales_followups', 'create')
                            <x-form.input name="next_due_at" label="Next follow-up on" type="datetime-local" hint="Leave blank if none is needed." />
                            <x-form.select name="next_purpose" label="Next purpose" :options="FollowUp::PURPOSES" :value="$followUp->purpose" />
                            <div class="md:col-span-2">
                                <x-form.input name="next_agenda" label="Next agenda" maxlength="1000" />
                            </div>
                        @endmodule
                    </div>
                    <div class="flex justify-end border-t border-slate-200 px-5 py-3"><button class="btn btn-primary"><x-icon name="check" class="h-4 w-4" /> Mark done</button></div>
                </form>
            @endmodule
        @endif

        <a href="{{ route('sales.followups.index') }}" class="inline-block text-sm font-semibold text-brand-600 hover:underline">← Back to follow-ups</a>
    </div>
@endsection
