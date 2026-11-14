<?php

namespace Database\Seeders;

use App\Models\BuyerQualification;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Demo data for Sales & CRM, covering every source, grade, quotation status, follow-up
 * bucket and retention state. Built on the products already in Product Master, through the
 * same workflow methods the screens use, with dates relative to today.
 *
 * Re-runnable: removes only previous demo records (notes / remarks start with "[Demo]") and
 * what hangs off them; real records and the masters are left alone. Not called by DatabaseSeeder:
 *     php artisan db:seed --class=DemoSalesSeeder
 * To remove the demo data without adding it back:
 *     DemoSalesSeeder::purge() from tinker
 */
class DemoSalesSeeder extends Seeder
{
    public const TAG = '[Demo]';

    protected Collection $products;

    protected Collection $team;

    protected User $admin;

    protected int $phone = 9000100000;

    public function run(): void
    {
        $this->products = Product::where('status', 'active')->orderBy('code')->get();
        if ($this->products->isEmpty()) {
            throw new RuntimeException('Add products to Product Master before seeding Sales & CRM demo data.');
        }

        $this->admin = User::where('is_super_admin', true)->firstOrFail();
        $this->team = User::where('is_active', true)->orderBy('id')->get()->filter(fn (User $u) => $u->canModule('sales_leads', 'show'))->values();

        DB::transaction(function () {
            self::purge();
            $customers = $this->customers();
            $this->leadsAndQuotations($customers);
            $this->customerFollowUps($customers);
        });
    }

    /** Deletes demo buyers and everything attached to them. */
    public static function purge(): void
    {
        $leads = Lead::where('notes', 'like', self::TAG.'%')->pluck('id');
        $customers = Customer::where('remarks', 'like', self::TAG.'%')->pluck('id');

        Quotation::whereIn('lead_id', $leads)->orWhereIn('customer_id', $customers)->delete();
        Lead::whereIn('id', $leads)->delete();
        // A demo customer whose ledger has vouchers keeps the ledger, so the books still add up.
        Ledger::whereIn('customer_id', $customers)->doesntHave('lines')->delete();
        Customer::whereIn('id', $customers)->delete();
    }

    // ------------------------------------------------------------- Customers

    /** One customer in each retention state, with order history by product. */
    protected function customers(): array
    {
        $make = fn (string $company, array $attrs, array $orders) => tap($this->customer($company, $attrs), fn (Customer $c) => $this->orders($c, $orders));

        return [
            'healthy' => $make('Saraswati Edible Oils', ['contact_person' => 'Meena Agarwal', 'city' => 'Indore', 'state' => 'Madhya Pradesh', 'industry' => 'edible_oil', 'buyer_type' => 'brand_owner', 'payment_terms' => 'credit_30', 'source' => 'exhibition', 'reorder_cycle_days' => 30], [
                [$this->find('bottle', 1000), [130, 100, 70, 40, 10], 50000, 6.40],
                [$this->find('bottle', 2000), [100, 40], 15000, 9.75],
            ]),
            'due' => $make('Ganga Pharma Labs', ['contact_person' => 'Dr. R. K. Verma', 'city' => 'Haridwar', 'state' => 'Uttarakhand', 'industry' => 'pharma', 'buyer_type' => 'contract', 'payment_terms' => 'part_advance', 'source' => 'referral'], [
                [$this->find('bottle', 50), [95, 65, 35], 200000, 1.15],
            ]),
            'at_risk' => $make('Shubh Homecare', ['contact_person' => 'Pankaj Jain', 'city' => 'Ahmedabad', 'state' => 'Gujarat', 'industry' => 'homecare', 'buyer_type' => 'distributor', 'payment_terms' => 'credit_60', 'source' => 'cold_call', 'reorder_cycle_days' => 30], [
                [$this->find('bottle', 500), [90, 60], 40000, 3.60],
            ]),
            'lapsed' => $make('Annapurna Foods', ['contact_person' => 'Sunita Rao', 'city' => 'Nagpur', 'state' => 'Maharashtra', 'industry' => 'food_beverage', 'buyer_type' => 'brand_owner', 'payment_terms' => 'on_delivery', 'source' => 'indiamart', 'reorder_cycle_days' => 30], [
                [$this->find('jar', 1000), [200, 150], 25000, 8.20],
            ]),
            'new' => $make('Kaveri Water Works', ['contact_person' => 'Suresh Iyer', 'city' => 'Mysuru', 'state' => 'Karnataka', 'industry' => 'water', 'buyer_type' => 'brand_owner', 'payment_terms' => 'advance', 'source' => 'website'], []),
            'inactive' => $make('Vaishali Lubricants', ['contact_person' => 'Amit Khanna', 'city' => 'Ludhiana', 'state' => 'Punjab', 'industry' => 'lubricants', 'buyer_type' => 'trader', 'payment_terms' => 'credit_30', 'source' => 'walk_in', 'status' => 'inactive'], [
                [$this->find('other', 5000), [300], 2000, 32.00],
            ]),
        ];
    }

    protected function customer(string $company, array $attrs): Customer
    {
        $customer = new Customer($attrs + [
            'company_name' => $company,
            'phone' => $this->nextPhone(),
            'email' => $this->email($company),
            'assigned_to' => $this->person(),
            'status' => 'active',
            'remarks' => self::TAG.' Sample customer for testing.',
        ]);
        $customer->created_by = $this->admin->id;
        $customer->save();

        return $customer;
    }

    protected function orders(Customer $customer, array $orders): void
    {
        foreach ($orders as [$product, $daysAgo, $quantity, $rate]) {
            foreach ($daysAgo as $i => $days) {
                $order = new CustomerOrder([
                    'customer_id' => $customer->id,
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'order_date' => $this->ago($days),
                    'po_number' => 'PO-'.(4100 + $customer->id * 10 + $i),
                    'quantity' => $quantity,
                    'unit' => 'pcs',
                    'rate' => $rate,
                    'amount' => round($quantity * $rate, 2),
                    'remarks' => self::TAG,
                ]);
                $order->created_by = $this->admin->id;
                $order->save();
            }
        }
    }

    // ------------------------------------------------------------- Leads → quotations

    protected function leadsAndQuotations(array $customers): void
    {
        // Fresh enquiries, not assessed yet.
        $this->lead('Nirmal Neer Beverages', 'water', 'indiamart', 'Buy lead #88213', 2, 'Vikas Tiwari', 'Bhopal', 'Madhya Pradesh', [
            $this->catalogue('bottle', 1000, 200000, 'per_month', 3.20, ['fill_product' => 'Mineral water']),
            $this->catalogue('bottle', 2000, 80000, 'per_month', 5.10, ['fill_product' => 'Mineral water']),
        ]);
        $this->lead('Herbal Glow Cosmetics', 'cosmetics', 'website', 'Contact form', 1, 'Neha Kapoor', 'Jaipur', 'Rajasthan', [
            $this->custom('bottle', 100, 'PET', 'round', 24, 'Screw (threaded)', 12, 'Amber', 25000, 'per_month', 4.25, ['closure' => 'pump', 'decoration' => 'screen_print', 'fill_product' => 'Face serum']),
        ]);

        // Spoken to, so a completed follow-up moved it from New to Contacted.
        $contacted = $this->lead('Kisan Shakti Agro Chem', 'agro', 'tradeindia', 'Catalogue enquiry', 6, 'Harpreet Gill', 'Bathinda', 'Punjab', [
            $this->custom('bottle', 1000, 'HDPE', 'round', 38, 'Screw (threaded)', 60, 'White', 30000, 'per_month', 11.50, ['closure' => 'cap', 'fill_product' => 'Pesticide']),
        ]);
        $this->followUp(['lead_id' => $contacted->id], 'call', 'requirement', -4, 'Understand the pesticide bottle spec', 'Needs UN-certified HDPE. Asked for a drawing.');
        $this->followUp(['lead_id' => $contacted->id], 'email', 'mould_approval', 3, 'Send the bottle drawing for approval');

        // Assessed, no quotation: one to nurture, one disqualified.
        $nurture = $this->lead('Apex Lubricants', 'lubricants', 'social_media', 'LinkedIn message', 9, 'Rohit Malhotra', 'Faridabad', 'Haryana', [
            $this->catalogue('other', 5000, 5000, 'per_month', null, ['fill_product' => 'Engine oil']),
        ]);
        $this->qualify($nurture, ['trader', 5000, 'unknown', 'trading', 'influencer', 'three_to_six', 'unknown'], 'nurture', 'Only exploring. Check again next quarter.');
        $this->followUp(['lead_id' => $nurture->id], 'call', 'general', 30, 'Check whether their plans have firmed up');

        $poor = $this->lead('Tiny Traders', 'other', 'walk_in', 'Visited the factory gate', 12, 'Mohan Lal', 'Pune', 'Maharashtra', [
            $this->custom('bottle', 30, 'PP', null, null, null, null, 'Pink', 500, 'one_time', 0.50),
        ]);
        $this->qualify($poor, ['retail', 42, 'below', 'not_feasible', 'unknown', 'exploring', 'unknown'], 'disqualified', 'Very small one-time order at a price below cost, in a size we cannot make.');

        // Qualified and ready to be quoted.
        $ready = $this->lead('Prakash Dairy Products', 'food_beverage', 'justdial', 'Phone enquiry', 4, 'Prakash Yadav', 'Agra', 'Uttar Pradesh', [
            $this->catalogue('bottle', 500, 45000, 'per_month', 3.40, ['closure' => 'cap', 'fill_product' => 'Flavoured milk']),
        ]);
        $this->qualify($ready, ['brand_owner', 45000, 'negotiable', 'existing', 'decision_maker', 'immediate', 'on_delivery'], 'qualified', null, 'Food grade, FSSAI');
        $this->followUp(['lead_id' => $ready->id], 'call', 'quotation', 1, 'Share the rate for the 500 ml bottle');

        // Lost by hand, before any quotation.
        $moq = $this->lead('Bharat Phenyl Works', 'homecare', 'other', 'Newspaper advertisement', 25, 'Jagdish Patel', 'Rajkot', 'Gujarat', [
            $this->catalogue('bottle', 500, 1000, 'one_time', 3.00),
        ]);
        $moq->update(['status' => 'lost', 'lost_reason' => 'moq']);

        // A repeat enquiry from an existing customer: draft quotation.
        $repeat = $this->lead('Saraswati Edible Oils', 'edible_oil', 'existing_customer', 'New 5 L pack for their mustard oil', 3, 'Meena Agarwal', 'Indore', 'Madhya Pradesh', [
            $this->catalogue('other', 5000, 8000, 'per_month', 30.00, ['closure' => 'cap', 'decoration' => 'label', 'fill_product' => 'Mustard oil']),
        ], ['phone' => $customers['healthy']->phone]);
        $repeat->forceFill(['customer_id' => $customers['healthy']->id])->save();
        $this->qualify($repeat, ['brand_owner', 8000, 'negotiable', 'trading', 'decision_maker', 'one_to_three', 'credit_30'], 'qualified');
        $this->quotation($repeat, 1, 15, [$this->quoted('other', 5000, 8000, 31.50, ['closure' => 'cap', 'decoration' => 'label', 'fill_product' => 'Mustard oil', 'moq' => 2000])], ['packing' => 'Pallet, 200 pcs']);

        // Sent, validity running out, sample with the buyer.
        $expiring = $this->lead('MedWell Lifesciences', 'pharma', 'referral', 'Referred by Ganga Pharma Labs', 18, 'Dr. Kavita Shah', 'Vadodara', 'Gujarat', [
            $this->catalogue('bottle', 50, 150000, 'per_month', 1.10, ['fill_product' => 'Cough syrup']),
        ]);
        $this->qualify($expiring, ['brand_owner', 150000, 'negotiable', 'existing', 'decision_maker', 'immediate', 'part_advance'], 'qualified', null, 'Pharma grade / GMP');
        $q = $this->quotation($expiring, 12, 15, [$this->quoted('bottle', 50, 150000, 1.18, ['fill_product' => 'Cough syrup', 'moq' => 50000])], ['delivery_days' => 15, 'sample_status' => 'sent', 'sample_sent_on' => $this->ago(11), 'packing' => 'Corrugated box, 500 pcs']);
        $this->advance($q, 'sent', 11);
        $this->followUp(['quotation_id' => $q->id, 'lead_id' => $expiring->id], 'call', 'sample_feedback', 0, 'Has the 50 ml sample passed their stability test?');

        // In negotiation with the sample approved.
        $negotiating = $this->lead('Shree Balaji Oil Mills', 'edible_oil', 'exhibition', 'PlastIndia 2026, stall B12', 20, 'Ramesh Gupta', 'Kota', 'Rajasthan', [
            $this->catalogue('bottle', 500, 100000, 'per_month', 3.30, ['closure' => 'flip_top', 'fill_product' => 'Soybean oil']),
            $this->catalogue('jar', 1000, 30000, 'per_month', 7.80, ['fill_product' => 'Ghee']),
        ], ['gstin' => '08ABCDE1234F1Z5']);
        $this->qualify($negotiating, ['brand_owner', 130000, 'negotiable', 'existing', 'decision_maker', 'immediate', 'part_advance'], 'qualified', null, 'Food grade', 'Local supplier in Kota');
        $q = $this->quotation($negotiating, 14, 30, [
            $this->quoted('bottle', 500, 100000, 3.55, ['closure' => 'flip_top', 'fill_product' => 'Soybean oil', 'moq' => 25000]),
            $this->quoted('jar', 1000, 30000, 8.20, ['fill_product' => 'Ghee', 'moq' => 10000]),
        ], ['discount_percent' => 2, 'freight_amount' => 12000, 'price_basis' => 'for_destination', 'delivery_days' => 12, 'sample_status' => 'approved', 'sample_sent_on' => $this->ago(13), 'payment_terms' => 'part_advance']);
        $this->advance($q, 'sent', 13);
        $this->advance($q, 'negotiation', 5, ['note' => 'Asking for ₹3.40 on the 500 ml bottle.']);
        $this->followUp(['quotation_id' => $q->id, 'lead_id' => $negotiating->id], 'visit', 'negotiation', 2, 'Meet Ramesh at the Kota plant to close the price');

        // Sent, past its validity and the follow-up was missed.
        $expired = $this->lead('Pure Drop Packaged Water', 'water', 'justdial', null, 40, 'Imran Sheikh', 'Lucknow', 'Uttar Pradesh', [
            $this->catalogue('jar', 20000, 3000, 'per_month', 145.00, ['fill_product' => 'Drinking water']),
        ]);
        $this->qualify($expired, ['distributor', 30000, 'negotiable', 'existing', 'influencer', 'one_to_three', 'credit_30'], 'qualified');
        $q = $this->quotation($expired, 30, 15, [$this->quoted('jar', 20000, 3000, 152.00, ['moq' => 500])], ['delivery_days' => 10]);
        $this->advance($q, 'sent', 29);
        $this->followUp(['quotation_id' => $q->id, 'lead_id' => $expired->id], 'call', 'quotation', -6, 'Quotation is about to lapse: get a decision');

        // Revised after negotiation: QTN-xxxx is replaced by QTN-xxxx-R1.
        $revised = $this->lead('CleanHome Products', 'homecare', 'cold_call', 'Field visit by sales team', 22, 'Farah Khan', 'Thane', 'Maharashtra', [
            $this->catalogue('bottle', 1000, 60000, 'per_month', 6.10, ['closure' => 'cap', 'decoration' => 'label', 'fill_product' => 'Floor cleaner']),
        ]);
        $this->qualify($revised, ['distributor', 60000, 'negotiable', 'existing', 'decision_maker', 'one_to_three', 'on_delivery'], 'qualified');
        $q = $this->quotation($revised, 16, 20, [$this->quoted('bottle', 1000, 60000, 6.75, ['closure' => 'cap', 'decoration' => 'label', 'fill_product' => 'Floor cleaner', 'moq' => 20000])], ['delivery_days' => 14]);
        $this->advance($q, 'sent', 15);
        $q = $q->fresh('items')->revise($this->admin);
        $q->items()->update(['rate' => 6.45, 'amount' => 387000]);
        $q->update(['remarks' => self::TAG.' Revised down after the buyer pushed back.']);
        $q->recalculate();
        $q = $q->fresh('items');
        $this->advance($q, 'sent', 4);
        $this->followUp(['quotation_id' => $q->id, 'lead_id' => $revised->id], 'whatsapp', 'quotation', 0, 'Has the revised rate of ₹6.45 been accepted?');

        // A custom bottle needing a new mould, with the charge on the quotation.
        $newMould = $this->lead('Royal Hair Oils', 'cosmetics', 'whatsapp', 'Message on the business number', 10, 'Sanjay Bansal', 'Kanpur', 'Uttar Pradesh', [
            $this->custom('bottle', 200, 'PET', 'oval', 24, 'Screw (threaded)', 16, 'Blue tint', 75000, 'per_month', 3.90, ['closure' => 'flip_top', 'decoration' => 'screen_print', 'fill_product' => 'Hair oil']),
        ]);
        $this->qualify($newMould, ['brand_owner', 75000, 'at_or_above', 'new_buyer_funded', 'decision_maker', 'immediate', 'part_advance'], 'qualified', null, null, 'Imports from Gujarat');
        $q = $this->quotation($newMould, 6, 21, [[
            'product_type' => 'bottle', 'capacity_ml' => 200, 'material' => 'PET', 'shape' => 'oval', 'neck_size_mm' => 24, 'neck_type' => 'Screw (threaded)', 'weight_grams' => 16, 'colour' => 'Blue tint',
            'closure' => 'flip_top', 'decoration' => 'screen_print', 'fill_product' => 'Hair oil',
            'quantity' => 75000, 'unit' => 'pcs', 'rate' => 4.15, 'moq' => 30000, 'mould_charge' => 185000,
        ]], ['delivery_days' => 20, 'mould_lead_days' => 45, 'sample_status' => 'pending', 'terms' => 'Mould charge is payable in advance and the mould stays reserved for this buyer.']);
        $this->advance($q, 'sent', 5);
        $this->followUp(['quotation_id' => $q->id, 'lead_id' => $newMould->id], 'call', 'mould_approval', -2, 'Get the mould drawing signed off');

        // Won: creates the customer and their first orders.
        $won = $this->lead('Sunrise Foods', 'food_beverage', 'indiamart', 'Buy lead #87540', 35, 'Pooja Nair', 'Kochi', 'Kerala', [
            $this->catalogue('jar', 1000, 40000, 'per_month', 7.90, ['closure' => 'cap', 'fill_product' => 'Pickle']),
        ], ['gstin' => '32ABCDE1234F1Z5']);
        $this->qualify($won, ['brand_owner', 40000, 'at_or_above', 'existing', 'decision_maker', 'immediate', 'advance'], 'qualified', null, 'Food grade, FSSAI');
        $q = $this->quotation($won, 28, 15, [$this->quoted('jar', 1000, 40000, 8.25, ['closure' => 'cap', 'fill_product' => 'Pickle', 'moq' => 10000])], ['delivery_days' => 10, 'sample_status' => 'approved', 'sample_sent_on' => $this->ago(26)]);
        $this->advance($q, 'sent', 27);
        $this->followUp(['quotation_id' => $q->id, 'lead_id' => $won->id], 'call', 'order_confirmation', -15, 'Confirm the PO', 'PO received. First batch in 10 days.');
        $this->advance($q, 'won', 12, ['po_number' => 'SF/PO/2231', 'won_on' => $this->ago(12)]);
        $q->customer->update(['remarks' => self::TAG.' Created when their quotation was won.']);

        // Won directly from an existing customer, no lead.
        $q = $this->quotation($customers['due'], 40, 15, [$this->quoted('bottle', 250, 50000, 2.35, ['moq' => 10000])], ['payment_terms' => 'part_advance']);
        $this->advance($q, 'sent', 39);
        $this->advance($q, 'won', 35, ['po_number' => 'GPL-778', 'won_on' => $this->ago(35)]);

        // Lost on price to a named competitor.
        $lost = $this->lead('Mahalaxmi Ghee Udyog', 'food_beverage', 'exhibition', 'Foodpro Expo 2026', 45, 'Girish Mehta', 'Rajkot', 'Gujarat', [
            $this->catalogue('jar', 1000, 20000, 'per_month', 6.90, ['fill_product' => 'Ghee']),
        ]);
        $this->qualify($lost, ['brand_owner', 20000, 'below', 'existing', 'decision_maker', 'one_to_three', 'credit_30'], 'qualified');
        $q = $this->quotation($lost, 38, 15, [$this->quoted('jar', 1000, 20000, 8.20)]);
        $this->advance($q, 'sent', 37);
        $this->advance($q, 'lost', 25, ['lost_reason' => 'price', 'competitor' => 'Rajkot Polypack']);

        // Lost because the sample failed.
        $rejected = $this->lead('Aroma Perfumers', 'cosmetics', 'website', 'Contact form', 33, 'Tanvi Desai', 'Surat', 'Gujarat', [
            $this->custom('bottle', 250, 'PET', 'round', 28, 'PCO 1881', 18, 'Frosted', 15000, 'per_quarter', 5.00, ['fill_product' => 'Room freshener']),
        ]);
        $this->qualify($rejected, ['contract', 5000, 'at_or_above', 'new_company_funded', 'influencer', 'one_to_three', 'on_delivery'], 'qualified');
        $q = $this->quotation($rejected, 28, 15, [$this->quoted('bottle', 250, 15000, 5.40)], ['sample_status' => 'rejected', 'sample_sent_on' => $this->ago(27)]);
        $this->advance($q, 'sent', 27);
        $this->advance($q, 'lost', 18, ['lost_reason' => 'sample_rejected', 'note' => 'Frosted finish did not match their artwork.']);
    }

    protected function customerFollowUps(array $customers): void
    {
        $healthy = $customers['healthy'];
        $this->followUp(['customer_id' => $healthy->id], 'call', 'feedback', -3, 'How did the last 1 L batch run on their filling line?', 'No leaks. Happy with the neck finish.', $this->find('bottle', 1000)->id);
        $this->followUp(['customer_id' => $healthy->id], 'call', 'payment', 0, 'Remind them about the invoice due this week');
        // A cancelled one, so that status shows too.
        $this->followUp(['customer_id' => $healthy->id], 'meeting', 'general', 5, 'Annual rate review')->forceFill(['status' => 'cancelled'])->save();

        // Already has a reminder; "due" does not, so the reminder button has work to do.
        $this->followUp(['customer_id' => $customers['at_risk']->id], 'call', 'reorder', -5, 'No order for two months: find out why', null, $this->find('bottle', 500)->id);
        $this->followUp(['customer_id' => $customers['lapsed']->id], 'visit', 'reorder', 6, 'Win-back visit with new jar rates', null, $this->find('jar', 1000)->id);
        $this->followUp(['customer_id' => $customers['new']->id], 'call', 'requirement', 1, 'Understand their 1 L and 2 L requirement');
    }

    // ------------------------------------------------------------- Builders

    /** @param  array  $lines  from catalogue() / custom() */
    protected function lead(string $company, string $industry, string $source, ?string $detail, int $daysAgo, string $contact, string $city, string $state, array $lines, array $extra = []): Lead
    {
        $lead = new Lead($extra + [
            'enquiry_date' => $this->ago($daysAgo),
            'company_name' => $company,
            'contact_person' => $contact,
            'phone' => $this->nextPhone(),
            'email' => $this->email($company),
            'city' => $city,
            'state' => $state,
            'industry' => $industry,
            'source' => $source,
            'source_detail' => $detail,
            'status' => 'new',
            'expected_close_date' => today()->addDays(30)->toDateString(),
            'assigned_to' => $this->person(),
            'notes' => self::TAG.' Sample enquiry for testing.',
        ]);
        $lead->created_by = $this->admin->id;
        $lead->save();

        foreach ($lines as $line) {
            $lead->items()->create($line);
        }

        return $lead->load('items');
    }

    /** $answers: buyer type, volume, price fit, mould fit, authority, timeline, payment. */
    protected function qualify(Lead $lead, array $answers, string $decision, ?string $reason = null, ?string $compliance = null, ?string $supplier = null): void
    {
        [$buyer, $volume, $price, $mould, $authority, $timeline, $payment] = $answers;

        $qualification = new BuyerQualification([
            'buyer_type' => $buyer, 'monthly_volume' => $volume, 'price_fit' => $price, 'mould_fit' => $mould, 'decision_authority' => $authority,
            'purchase_timeline' => $timeline, 'payment_terms' => $payment, 'gst_verified' => (bool) $lead->gstin, 'sample_required' => $decision === 'qualified',
            'compliance_required' => $compliance, 'current_supplier' => $supplier, 'decision' => $decision, 'decision_reason' => $reason,
        ]);
        $qualification->lead_id = $lead->id;
        $qualification->qualified_by = $lead->assigned_to ?? $this->admin->id;
        $qualification->save();

        $lead->applyQualificationDecision($decision);
    }

    protected function quotation(Lead|Customer $buyer, int $daysAgo, int $validDays, array $lines, array $extra = []): Quotation
    {
        $isLead = $buyer instanceof Lead;

        $quotation = new Quotation($extra + [
            'lead_id' => $isLead ? $buyer->id : null,
            'customer_id' => $isLead ? $buyer->customer_id : $buyer->id,
            'quote_date' => $this->ago($daysAgo),
            'valid_until' => today()->subDays($daysAgo)->addDays($validDays)->toDateString(),
            'price_basis' => 'ex_works',
            'payment_terms' => 'part_advance',
            'sample_status' => 'not_required',
            'gst_percent' => 18,
            'assigned_to' => $buyer->assigned_to,
            'remarks' => self::TAG.' Sample quotation for testing.',
        ]);
        $quotation->status = 'draft';
        $quotation->created_by = $this->admin->id;
        $quotation->save();

        foreach ($lines as $line) {
            $quotation->items()->create($line);
        }
        $quotation->recalculate();
        $quotation->logStatus(null, 'draft', null, $this->admin);
        $this->backdateLastLog($quotation, $daysAgo);
        $quotation->lead?->syncStatusFromQuotations();

        return $quotation->load('items');
    }

    /** Moves a quotation on through its real workflow, then dates the step in the past. */
    protected function advance(Quotation $quotation, string $to, int $daysAgo, array $details = []): void
    {
        $quotation->changeStatus($to, $details, $this->admin);

        if ($to === 'sent') {
            $quotation->forceFill(['sent_on' => $this->ago($daysAgo)])->save();
        }
        $this->backdateLastLog($quotation, $daysAgo);
    }

    protected function backdateLastLog(Quotation $quotation, int $daysAgo): void
    {
        $quotation->statusLogs()->latest('id')->first()->forceFill(['created_at' => today()->subDays($daysAgo)->setTime(11, 30)])->save();
    }

    /** $dueInDays may be negative. Passing an $outcome marks it done. */
    protected function followUp(array $on, string $type, string $purpose, int $dueInDays, string $agenda, ?string $outcome = null, ?int $productId = null): FollowUp
    {
        $followUp = new FollowUp($on + [
            'type' => $type,
            'purpose' => $purpose,
            'due_at' => today()->addDays($dueInDays)->setTime(10 + abs($dueInDays) % 7, 30),
            'agenda' => $agenda,
            'product_id' => $productId,
            'assigned_to' => $this->person(),
        ]);
        $followUp->created_by = $this->admin->id;
        $followUp->save();

        if ($outcome) {
            $followUp->complete($outcome, $this->admin);
        }

        return $followUp;
    }

    /** A lead line for a Product Master record. */
    protected function catalogue(string $type, float $ml, int $quantity, string $basis, ?float $target, array $extra = []): array
    {
        return $extra + ['product_id' => $this->find($type, $ml)->id, 'product_type' => $type, 'quantity' => $quantity, 'qty_basis' => $basis, 'target_price' => $target];
    }

    /** A lead line for a spec that isn't in Product Master. */
    protected function custom(string $type, float $ml, string $material, ?string $shape, ?float $neck, ?string $neckType, ?float $grams, string $colour, int $quantity, string $basis, ?float $target, array $extra = []): array
    {
        return $extra + [
            'product_type' => $type, 'capacity_ml' => $ml, 'material' => $material, 'shape' => $shape, 'neck_size_mm' => $neck, 'neck_type' => $neckType,
            'weight_grams' => $grams, 'colour' => $colour, 'quantity' => $quantity, 'qty_basis' => $basis, 'target_price' => $target,
        ];
    }

    /** A quotation line for a Product Master record. */
    protected function quoted(string $type, float $ml, int $quantity, float $rate, array $extra = []): array
    {
        return $extra + ['product_id' => $this->find($type, $ml)->id, 'product_type' => $type, 'quantity' => $quantity, 'unit' => 'pcs', 'rate' => $rate];
    }

    /** The closest product of this type by volume, so the demo works with whatever the master holds. */
    protected function find(string $type, float $ml): Product
    {
        $sameType = $this->products->where('product_type', $type);

        return ($sameType->isNotEmpty() ? $sameType : $this->products)
            ->sortBy(fn (Product $p) => abs((float) $p->capacity_ml - $ml))
            ->first();
    }

    /** Hands records out across the people with Sales & CRM access. */
    protected function person(): int
    {
        static $turn = 0;

        return $this->team[$turn++ % max(1, $this->team->count())]->id ?? $this->admin->id;
    }

    protected function ago(int $days): string
    {
        return Carbon::today()->subDays($days)->toDateString();
    }

    protected function nextPhone(): string
    {
        return (string) ++$this->phone;
    }

    protected function email(string $company): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '.', $company)).'@example.com';
    }
}
