<?php

namespace App\Http\Controllers\Sales\Concerns;

use App\Models\Customer;
use App\Models\Lead;
use App\Support\BuyerScore;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The customer fields shared by Customer Master and Customer Retention. */
trait HandlesCustomerDetails
{
    protected function customerRules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'gstin' => ['nullable', 'regex:/^[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z][0-9A-Za-z][Zz][0-9A-Za-z]$/'],
            'industry' => ['nullable', Rule::in(array_keys(Lead::INDUSTRIES))],
            'buyer_type' => ['nullable', Rule::in(array_keys(BuyerScore::options('buyer_type')))],
            'payment_terms' => ['nullable', Rule::in(array_keys(BuyerScore::options('payment_terms')))],
            'source' => ['nullable', Rule::in(array_keys(Lead::SOURCES))],
            'reorder_cycle_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', Rule::in(array_keys(Customer::STATUSES))],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function customerMessages(): array
    {
        return [
            'phone.regex' => 'Enter a valid mobile number.',
            'gstin.regex' => 'GSTIN must look like 27ABCDE1234F1Z5.',
        ];
    }

    protected function customerAttributes(): array
    {
        return [
            'company_name' => 'company name',
            'phone' => 'mobile number',
            'gstin' => 'GSTIN',
            'reorder_cycle_days' => 'reorder cycle',
        ];
    }

    protected function customerData(Request $request): array
    {
        return $request->only([
            'company_name', 'contact_person', 'phone', 'email', 'city', 'state', 'address', 'industry',
            'buyer_type', 'payment_terms', 'source', 'reorder_cycle_days', 'assigned_to', 'status', 'remarks',
        ]) + ['gstin' => $request->filled('gstin') ? strtoupper($request->gstin) : null];
    }
}
