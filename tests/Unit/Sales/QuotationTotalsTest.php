<?php

namespace Tests\Unit\Sales;

use App\Support\QuotationTotals;
use PHPUnit\Framework\TestCase;

class QuotationTotalsTest extends TestCase
{
    public function test_line_amount_handles_sub_rupee_bottle_rates(): void
    {
        $this->assertSame(18500.0, QuotationTotals::lineAmount(10000, 1.85));
        $this->assertSame(0.86, QuotationTotals::lineAmount(1, 0.855));
        $this->assertSame(1234.5, QuotationTotals::lineAmount('1000', '1.2345'));
    }

    public function test_simple_goods_and_gst(): void
    {
        $totals = QuotationTotals::compute([['quantity' => 10000, 'rate' => 2]], 0, 0, 18);

        $this->assertSame(20000.0, $totals['subtotal']);
        $this->assertSame(0.0, $totals['discount_amount']);
        $this->assertSame(3600.0, $totals['tax_amount']);
        $this->assertSame(23600.0, $totals['total']);
    }

    public function test_discount_touches_goods_only_but_gst_covers_everything(): void
    {
        $totals = QuotationTotals::compute([
            ['quantity' => 10000, 'rate' => 2, 'mould_charge' => 50000],
            ['quantity' => 5000, 'rate' => 4],
        ], 10, 1000, 18);

        $this->assertSame(40000.0, $totals['subtotal']);
        $this->assertSame(4000.0, $totals['discount_amount']);   // 10% of goods, not of the mould
        $this->assertSame(50000.0, $totals['mould_total']);
        // Taxable = 40000 − 4000 + 50000 + 1000 = 87000
        $this->assertSame(15660.0, $totals['tax_amount']);
        $this->assertSame(102660.0, $totals['total']);
    }

    public function test_no_lines_means_zero_apart_from_freight(): void
    {
        $this->assertSame(0.0, QuotationTotals::compute([])['total']);
        $this->assertSame(118.0, QuotationTotals::compute([], 0, 100, 18)['total']);
    }

    public function test_blank_values_from_a_form_are_treated_as_zero(): void
    {
        $totals = QuotationTotals::compute([['quantity' => '100', 'rate' => '1.5', 'mould_charge' => null]], null, null, null);

        $this->assertSame(150.0, $totals['total']);
    }

    public function test_full_discount_and_rounding(): void
    {
        $this->assertSame(0.0, QuotationTotals::compute([['quantity' => 10, 'rate' => 3]], 100, 0, 18)['total']);

        $totals = QuotationTotals::compute([['quantity' => 3, 'rate' => 3.333]], 0, 0, 18);
        $this->assertSame(10.0, $totals['subtotal']);
        $this->assertSame(1.8, $totals['tax_amount']);
        $this->assertSame(11.8, $totals['total']);
    }
}
