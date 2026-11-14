{{-- An order's details, lines and totals, shared by the order and approval pages. Expects $order; optional $lastRates. --}}
@use('App\Models\Quotation')
@use('App\Support\BuyerScore')
@use('App\Support\Money')
<dl class="grid gap-5 p-5 sm:grid-cols-2 lg:grid-cols-4">
    <x-detail label="Customer">
        @module('orders_customers', 'view')
            <a href="{{ route('orders.customers.show', $order->customer) }}" class="font-semibold text-brand-600 hover:underline">{{ $order->customer->company_name }}</a>
        @else
            {{ $order->customer->company_name }}
        @endmodule
    </x-detail>
    <x-detail label="Order date">{{ $order->order_date->format('d M Y') }}</x-detail>
    <x-detail label="Required by">{{ $order->required_by?->format('d M Y') }}</x-detail>
    <x-detail label="Customer's PO">{{ collect([$order->po_number, $order->po_date?->format('d M Y')])->filter()->join(' · ') }}</x-detail>
    <x-detail label="Payment terms">{{ BuyerScore::label('payment_terms', $order->payment_terms) }}</x-detail>
    <x-detail label="Price basis">{{ Quotation::PRICE_BASES[$order->price_basis] ?? '' }}</x-detail>
    <x-detail label="Packing">{{ $order->packing }}</x-detail>
    <x-detail label="From quotation">
        @if ($order->quotation)
            @module('sales_quotations', 'view')
                <a href="{{ route('sales.quotations.show', $order->quotation) }}" class="font-mono text-xs font-semibold text-brand-600 hover:underline">{{ $order->quotation->number() }}</a>
            @else
                {{ $order->quotation->number() }}
            @endmodule
        @endif
    </x-detail>
    <x-detail label="Ship to" class="sm:col-span-2"><span class="whitespace-pre-line">{{ $order->shipping_address }}</span></x-detail>
    <x-detail label="Entered by">{{ $order->creator?->name }}</x-detail>
    <x-detail label="Handled by">{{ $order->assignee?->name }}</x-detail>
    @if ($order->remarks)<x-detail label="Remarks" class="sm:col-span-2 lg:col-span-4"><span class="whitespace-pre-line">{{ $order->remarks }}</span></x-detail>@endif
</dl>

<div class="overflow-x-auto border-t border-slate-200">
    <table class="table-classic">
        <thead>
        <tr>
            <th>Product</th>
            <th class="text-right">Qty</th>
            <th class="text-right">Rate</th>
            @isset($lastRates)<th class="text-right">Last rate</th>@endisset
            <th class="text-right">Mould charge</th>
            <th class="text-right">Amount</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($order->items as $line)
            <tr>
                <td>
                    <span class="font-mono text-xs font-semibold text-navy-800">{{ $line->product_code }}</span>
                    <div class="font-semibold text-slate-800">{{ $line->description }}</div>
                </td>
                <td class="text-right tabular-nums whitespace-nowrap">{{ Money::format($line->quantity, 0) }} <span class="text-xs text-slate-500">{{ $line->unitLabel() }}</span></td>
                <td class="text-right tabular-nums whitespace-nowrap">₹{{ Money::format($line->rate, 3) }}</td>
                @isset($lastRates)
                    <td class="text-right tabular-nums whitespace-nowrap text-slate-500">{{ isset($lastRates[$line->product_id]) ? '₹'.Money::format($lastRates[$line->product_id], 3) : 'First order' }}</td>
                @endisset
                <td class="text-right tabular-nums whitespace-nowrap">{{ (float) $line->mould_charge ? '₹'.Money::format($line->mould_charge) : '—' }}</td>
                <td class="text-right font-semibold tabular-nums whitespace-nowrap">₹{{ Money::format($line->amount) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<dl class="ml-auto max-w-sm space-y-1.5 p-5 text-sm">
    <div class="flex justify-between"><dt class="text-slate-500">Goods</dt><dd class="tabular-nums">₹{{ Money::format($order->subtotal) }}</dd></div>
    @if ((float) $order->discount_amount)<div class="flex justify-between"><dt class="text-slate-500">Discount ({{ (float) $order->discount_percent }}%)</dt><dd class="tabular-nums">−₹{{ Money::format($order->discount_amount) }}</dd></div>@endif
    @if ((float) $order->mould_total)<div class="flex justify-between"><dt class="text-slate-500">Mould charges</dt><dd class="tabular-nums">₹{{ Money::format($order->mould_total) }}</dd></div>@endif
    @if ((float) $order->freight_amount)<div class="flex justify-between"><dt class="text-slate-500">Freight</dt><dd class="tabular-nums">₹{{ Money::format($order->freight_amount) }}</dd></div>@endif
    <div class="flex justify-between"><dt class="text-slate-500">GST ({{ (float) $order->gst_percent }}%)</dt><dd class="tabular-nums">₹{{ Money::format($order->tax_amount) }}</dd></div>
    <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-bold text-navy-900"><dt>Total</dt><dd class="tabular-nums">₹{{ Money::format($order->total) }}</dd></div>
</dl>
