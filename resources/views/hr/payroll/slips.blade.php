{{-- Printable salary slip(s): one per printed A4 page. Standalone page (no sidebar) so it prints cleanly. --}}
@use('App\Support\Money')
@use('App\Models\Payslip')
@use('App\Models\Employee')
@php
    $company = config('company');
    $m = fn ($v) => Money::format($v);
    $title = $payslips->count() === 1 ? 'Salary Slip – '.$payslips->first()->snap('name').' – '.$run->label() : 'Salary Slips – '.$run->label();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4; margin: 12mm; }
        @media print { .slip { break-after: page; } .slip:last-child { break-after: auto; } }
    </style>
</head>
<body class="bg-slate-100 font-sans text-slate-800 print:bg-white">
    <div class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-6 py-3 shadow-sm print:hidden">
        <div class="text-sm font-semibold text-slate-700">{{ $title }}</div>
        <div class="flex gap-2">
            <a href="{{ route('hr.payroll.show', $run) }}" class="btn btn-secondary btn-sm">Back to payroll</a>
            <button type="button" onclick="window.print()" class="btn btn-primary btn-sm">Print / Save as PDF</button>
        </div>
    </div>

    @foreach ($payslips as $slip)
        <div class="slip relative mx-auto my-6 max-w-[800px] border border-slate-300 bg-white p-8 text-[13px] print:my-0 print:max-w-none print:border-0 print:p-0">
            @unless ($run->isFinalized())
                <div class="pointer-events-none absolute inset-0 flex items-center justify-center text-8xl font-black tracking-widest text-slate-200/60 -rotate-12 select-none">DRAFT</div>
            @endunless

            <div class="relative">
                {{-- Header --}}
                <div class="flex items-start justify-between gap-4 border-b-2 border-navy-900 pb-3">
                    <div class="flex items-center gap-3">
                        <img src="{{ asset('storage/logo.png') }}" alt="" class="h-14 w-14 object-contain" onerror="this.remove()">
                        <div>
                            <div class="text-xl font-bold text-navy-900">{{ $company['name'] }}</div>
                            @if ($company['address'])<div class="text-xs whitespace-pre-line text-slate-600">{{ $company['address'] }}</div>@endif
                            <div class="text-xs text-slate-600">{{ collect([$company['phone'] ? 'Ph: '.$company['phone'] : null, $company['email'], $company['gstin'] ? 'GSTIN: '.$company['gstin'] : null])->filter()->join(' · ') }}</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-base font-bold tracking-wide text-navy-900 uppercase">Salary Slip</div>
                        <div class="text-sm text-slate-600">for the month of <strong>{{ $run->label() }}</strong></div>
                    </div>
                </div>

                {{-- Employee details --}}
                <table class="mt-4 w-full border-collapse">
                    @foreach ([
                        ['Employee Code', $slip->snap('code'), 'Date of Joining', $slip->snap('joining_date') ? \Illuminate\Support\Carbon::parse($slip->snap('joining_date'))->format('d M Y') : null],
                        ['Employee Name', $slip->snap('name'), 'PAN', $slip->snap('pan')],
                        ['Designation', $slip->snap('designation'), 'UAN', $slip->snap('uan')],
                        ['Department', $slip->snap('department'), 'PF No.', $slip->snap('pf_number')],
                        ['Bank', $slip->snap('bank_name'), 'ESI No.', $slip->snap('esi_number')],
                        ['Bank A/c No.', $slip->snap('bank_account'), 'IFSC', $slip->snap('ifsc')],
                    ] as [$l1, $v1, $l2, $v2])
                        <tr>
                            <td class="w-[18%] border border-slate-300 bg-slate-50 px-2 py-1 font-semibold">{{ $l1 }}</td>
                            <td class="w-[32%] border border-slate-300 px-2 py-1">{{ $v1 ?: '—' }}</td>
                            <td class="w-[18%] border border-slate-300 bg-slate-50 px-2 py-1 font-semibold">{{ $l2 }}</td>
                            <td class="w-[32%] border border-slate-300 px-2 py-1">{{ $v2 ?: '—' }}</td>
                        </tr>
                    @endforeach
                </table>

                {{-- Attendance --}}
                <table class="mt-3 w-full border-collapse text-center">
                    <tr class="bg-slate-50 font-semibold">
                        <td class="border border-slate-300 px-2 py-1">Days in Month</td>
                        <td class="border border-slate-300 px-2 py-1">Paid Days</td>
                        <td class="border border-slate-300 px-2 py-1">LOP Days</td>
                        <td class="border border-slate-300 px-2 py-1">Overtime Hours</td>
                    </tr>
                    <tr>
                        <td class="border border-slate-300 px-2 py-1">{{ $slip->days_in_month }}</td>
                        <td class="border border-slate-300 px-2 py-1">{{ (float) $slip->paid_days }}</td>
                        <td class="border border-slate-300 px-2 py-1">{{ (float) $slip->lop_days }}</td>
                        <td class="border border-slate-300 px-2 py-1">{{ (float) $slip->overtime_hours }}</td>
                    </tr>
                </table>

                {{-- Earnings & deductions --}}
                @php
                    $earnings = collect(Payslip::EARNINGS)->filter(fn ($l, $f) => (float) $slip->{$f} > 0 || array_key_exists($f, Employee::SALARY_COMPONENTS) && (float) $slip->rate($f) > 0);
                    $deductions = collect(Payslip::DEDUCTIONS)->filter(fn ($l, $f) => (float) $slip->{$f} > 0);
                    $lines = max($earnings->count(), $deductions->count(), 1);
                    [$eKeys, $dKeys] = [$earnings->keys()->values(), $deductions->keys()->values()];
                @endphp
                <table class="mt-3 w-full border-collapse">
                    <thead>
                    <tr class="bg-navy-900 text-white">
                        <th class="border border-navy-900 px-2 py-1.5 text-left">Earnings</th>
                        <th class="border border-navy-900 px-2 py-1.5 text-right">Rate (₹)</th>
                        <th class="border border-navy-900 px-2 py-1.5 text-right">Amount (₹)</th>
                        <th class="border border-navy-900 px-2 py-1.5 text-left">Deductions</th>
                        <th class="border border-navy-900 px-2 py-1.5 text-right">Amount (₹)</th>
                    </tr>
                    </thead>
                    <tbody>
                    @for ($i = 0; $i < $lines; $i++)
                        @php [$e, $d] = [$eKeys[$i] ?? null, $dKeys[$i] ?? null]; @endphp
                        <tr>
                            <td class="border border-slate-300 px-2 py-1">{{ $e ? Payslip::EARNINGS[$e].($e === 'overtime_pay' ? ' ('.(float) $slip->overtime_hours.' h)' : '') : '' }}</td>
                            <td class="border border-slate-300 px-2 py-1 text-right tabular-nums text-slate-500">{{ $e && $slip->rate($e) !== null ? $m($slip->rate($e)) : '' }}</td>
                            <td class="border border-slate-300 px-2 py-1 text-right tabular-nums">{{ $e ? $m($slip->{$e}) : '' }}</td>
                            <td class="border border-slate-300 px-2 py-1">{{ $d ? Payslip::DEDUCTIONS[$d] : '' }}</td>
                            <td class="border border-slate-300 px-2 py-1 text-right tabular-nums">{{ $d ? $m($slip->{$d}) : '' }}</td>
                        </tr>
                    @endfor
                    <tr class="bg-slate-50 font-bold">
                        <td class="border border-slate-300 px-2 py-1.5">Gross Earnings</td>
                        <td class="border border-slate-300 px-2 py-1.5 text-right tabular-nums text-slate-500">{{ $m($slip->fixed_gross) }}</td>
                        <td class="border border-slate-300 px-2 py-1.5 text-right tabular-nums">{{ $m($slip->gross_earnings) }}</td>
                        <td class="border border-slate-300 px-2 py-1.5">Total Deductions</td>
                        <td class="border border-slate-300 px-2 py-1.5 text-right tabular-nums">{{ $m($slip->total_deductions) }}</td>
                    </tr>
                    </tbody>
                </table>

                {{-- Net pay --}}
                <div class="mt-3 flex items-center justify-between border-2 border-navy-900 px-3 py-2">
                    <div>
                        <div class="text-xs font-semibold text-slate-500 uppercase">Net Pay</div>
                        <div class="text-xs text-slate-700 italic">{{ Money::inWords($slip->net_pay) }}</div>
                    </div>
                    <div class="text-xl font-bold tabular-nums text-navy-900">₹ {{ $m($slip->net_pay) }}</div>
                </div>

                @if ((float) $slip->employer_pf || (float) $slip->employer_esi || $slip->remarks)
                    <div class="mt-2 text-xs text-slate-600">
                        @if ((float) $slip->employer_pf || (float) $slip->employer_esi)
                            Employer contribution (not deducted): PF ₹{{ $m($slip->employer_pf) }}, ESI ₹{{ $m($slip->employer_esi) }}.
                        @endif
                        @if ($slip->remarks) Note: {{ $slip->remarks }} @endif
                    </div>
                @endif

                <div class="mt-10 flex items-end justify-between text-xs text-slate-600">
                    <div>Employee's signature</div>
                    <div class="text-right">For {{ $company['name'] }}<br><br>Authorised signatory</div>
                </div>
                <p class="mt-6 border-t border-slate-200 pt-2 text-center text-[11px] text-slate-400">This is a computer-generated salary slip.</p>
            </div>
        </div>
    @endforeach
</body>
</html>
