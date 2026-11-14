{{-- Printable quotation for the buyer. Standalone page (no sidebar); internal remarks and mould codes stay off it. --}}
@use('App\Models\Quotation')
@use('App\Models\Shape')
@use('App\Support\BuyerScore')
@use('App\Support\Money')
@php
    $company = config('company');
    $party = $quotation->buyerParty();
    $title = 'Quotation '.$quotation->number().' – '.$quotation->buyerName();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css'])
    <style>@page { size: A4; margin: 12mm; }</style>
</head>
<body class="bg-slate-100 font-sans text-slate-800 print:bg-white">
    <div class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-6 py-3 shadow-sm print:hidden">
        <div class="text-sm font-semibold text-slate-700">{{ $title }}</div>
        <div class="flex gap-2">
            <a href="{{ route('sales.quotations.show', $quotation) }}" class="btn btn-secondary btn-sm">Back</a>
            <button type="button" onclick="window.print()" class="btn btn-primary btn-sm">Print / Save as PDF</button>
        </div>
    </div>

    <div class="mx-auto my-6 max-w-[210mm] bg-white p-8 shadow print:m-0 print:max-w-none print:p-0 print:shadow-none">
        <div class="flex items-start justify-between gap-6 border-b-2 border-navy-900 pb-4">
            <div>
                <div class="text-xl font-bold text-navy-900">{{ $company['name'] }}</div>
                <div class="text-xs whitespace-pre-line text-slate-600">{{ $company['address'] }}</div>
                <div class="text-xs text-slate-600">{{ collect([$company['phone'], $company['email'], $company['gstin'] ? 'GSTIN: '.$company['gstin'] : null])->filter()->join(' · ') }}</div>
            </div>
            <div class="text-right">
                <div class="text-lg font-bold tracking-wide text-navy-900 uppercase">Quotation</div>
                <div class="font-mono text-sm font-semibold">{{ $quotation->number() }}</div>
                <div class="text-xs text-slate-600">Date: {{ $quotation->quote_date->format('d M Y') }}</div>
                <div class="text-xs text-slate-600">Valid until: {{ $quotation->valid_until->format('d M Y') }}</div>
            </div>
        </div>

        <div class="mt-4 text-sm">
            <div class="text-xs font-semibold tracking-wide text-slate-500 uppercase">To</div>
            <div class="font-semibold">{{ $quotation->buyerName() }}</div>
            @if ($party?->contact_person)<div>Kind attention: {{ $party->contact_person }}</div>@endif
            <div class="text-slate-600">{{ collect([$party?->address, $party?->city, $party?->state])->filter()->join(', ') }}</div>
            <div class="text-slate-600">{{ collect([$party?->phone, $party?->email, $party?->gstin ? 'GSTIN: '.$party->gstin : null])->filter()->join(' · ') }}</div>
        </div>

        <table class="mt-5 w-full border-collapse text-sm">
            <thead>
            <tr class="bg-slate-100 text-left text-xs font-bold text-slate-600 uppercase">
                <th class="border border-slate-300 px-2 py-1.5">#</th>
                <th class="border border-slate-300 px-2 py-1.5">Product</th>
                <th class="border border-slate-300 px-2 py-1.5 text-right">Qty</th>
                <th class="border border-slate-300 px-2 py-1.5 text-right">Rate (₹)</th>
                <th class="border border-slate-300 px-2 py-1.5 text-right">Amount (₹)</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($quotation->items as $i => $line)
                <tr>
                    <td class="border border-slate-300 px-2 py-1.5 align-top">{{ $i + 1 }}</td>
                    <td class="border border-slate-300 px-2 py-1.5 align-top">
                        <div class="font-semibold">{{ $line->description }}</div>
                        <div class="text-xs text-slate-600">{{ collect([$line->requirementNote(), $line->moq ? 'MOQ: '.Money::format($line->moq, 0).' '.$line->unitLabel() : null])->filter()->join(' · ') }}</div>
                    </td>
                    <td class="border border-slate-300 px-2 py-1.5 text-right align-top whitespace-nowrap">{{ Money::format($line->quantity, 0) }} {{ $line->unitLabel() }}</td>
                    <td class="border border-slate-300 px-2 py-1.5 text-right align-top">{{ Money::format($line->rate, 3) }}</td>
                    <td class="border border-slate-300 px-2 py-1.5 text-right align-top">{{ Money::format($line->amount) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <dl class="mt-3 ml-auto max-w-xs space-y-1 text-sm">
            <div class="flex justify-between"><dt>Goods</dt><dd>₹{{ Money::format($quotation->subtotal) }}</dd></div>
            @if ((float) $quotation->discount_amount)<div class="flex justify-between"><dt>Discount ({{ (float) $quotation->discount_percent }}%)</dt><dd>−₹{{ Money::format($quotation->discount_amount) }}</dd></div>@endif
            @if ((float) $quotation->mould_total)<div class="flex justify-between"><dt>Mould charges (one-time)</dt><dd>₹{{ Money::format($quotation->mould_total) }}</dd></div>@endif
            @if ((float) $quotation->freight_amount)<div class="flex justify-between"><dt>Freight</dt><dd>₹{{ Money::format($quotation->freight_amount) }}</dd></div>@endif
            <div class="flex justify-between"><dt>GST ({{ (float) $quotation->gst_percent }}%)</dt><dd>₹{{ Money::format($quotation->tax_amount) }}</dd></div>
            <div class="flex justify-between border-t-2 border-navy-900 pt-1 font-bold"><dt>Total</dt><dd>₹{{ Money::format($quotation->total) }}</dd></div>
        </dl>
        <p class="mt-2 text-xs text-slate-600">{{ Money::inWords($quotation->total) }}</p>

        <div class="mt-5 text-sm">
            <div class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Terms</div>
            <ul class="mt-1 list-disc pl-5 text-slate-700">
                <li>Price basis: {{ Quotation::PRICE_BASES[$quotation->price_basis] ?? '' }}</li>
                @if ($quotation->payment_terms && $quotation->payment_terms !== 'unknown')<li>Payment: {{ BuyerScore::label('payment_terms', $quotation->payment_terms) }}</li>@endif
                @if ($quotation->delivery_days !== null)<li>Delivery: within {{ $quotation->delivery_days }} days of order confirmation</li>@endif
                @if ($quotation->mould_lead_days !== null)<li>New mould: {{ $quotation->mould_lead_days }} days from mould approval</li>@endif
                @if ($quotation->packing)<li>Packing: {{ $quotation->packing }}</li>@endif
            </ul>
            @if ($quotation->terms)<p class="mt-2 whitespace-pre-line text-slate-700">{{ $quotation->terms }}</p>@endif
        </div>

        <div class="mt-12 text-right text-sm">
            <div>For {{ $company['name'] }}</div>
            <div class="mt-10 text-xs text-slate-500">Authorised signatory</div>
        </div>
    </div>
</body>
</html>
