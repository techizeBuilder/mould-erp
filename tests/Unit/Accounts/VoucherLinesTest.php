<?php

namespace Tests\Unit\Accounts;

use App\Support\VoucherLines;
use PHPUnit\Framework\TestCase;

class VoucherLinesTest extends TestCase
{
    protected const GST = ['cgst' => 91, 'sgst' => 92, 'igst' => 93];

    public function test_simple_entry_debits_one_ledger_and_credits_the_other(): void
    {
        $this->assertSame([
            ['ledger_id' => 1, 'debit' => 500.5, 'credit' => 0.0],
            ['ledger_id' => 2, 'debit' => 0.0, 'credit' => 500.5],
        ], VoucherLines::simple(1, 2, '500.50'));
    }

    public function test_gst_within_state_splits_in_half(): void
    {
        $this->assertSame(['cgst' => 900.0, 'sgst' => 900.0, 'igst' => 0.0, 'tax' => 1800.0, 'total' => 11800.0], VoucherLines::gst(10000, 18, false));
    }

    public function test_gst_across_states_is_all_igst(): void
    {
        $this->assertSame(['cgst' => 0.0, 'sgst' => 0.0, 'igst' => 1800.0, 'tax' => 1800.0, 'total' => 11800.0], VoucherLines::gst(10000, 18, true));
    }

    public function test_an_odd_paisa_of_tax_is_not_lost_in_the_split(): void
    {
        // 18% of 100.05 = 18.01, which doesn't halve evenly.
        $gst = VoucherLines::gst(100.05, 18, false);

        $this->assertSame(18.01, $gst['tax']);
        $this->assertSame(1801, VoucherLines::paise($gst['cgst']) + VoucherLines::paise($gst['sgst']));
    }

    public function test_zero_rate_has_no_tax(): void
    {
        $this->assertSame(['cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0, 'tax' => 0.0, 'total' => 1000.0], VoucherLines::gst(1000, 0, false));
    }

    public function test_sale_debits_the_customer_and_credits_sales_and_output_gst(): void
    {
        $lines = VoucherLines::sale(1, 2, 1000, 18, false, self::GST);

        $this->assertSame([
            ['ledger_id' => 1, 'debit' => 1180.0, 'credit' => 0.0],
            ['ledger_id' => 2, 'debit' => 0.0, 'credit' => 1000.0],
            ['ledger_id' => 91, 'debit' => 0.0, 'credit' => 90.0],
            ['ledger_id' => 92, 'debit' => 0.0, 'credit' => 90.0],
        ], $lines);
        $this->assertTrue(VoucherLines::isBalanced($lines));
    }

    public function test_purchase_debits_purchases_and_input_gst_and_credits_the_supplier(): void
    {
        $lines = VoucherLines::purchase(1, 2, 1000, 5, true, self::GST);

        $this->assertSame([
            ['ledger_id' => 2, 'debit' => 1000.0, 'credit' => 0.0],
            ['ledger_id' => 93, 'debit' => 50.0, 'credit' => 0.0],
            ['ledger_id' => 1, 'debit' => 0.0, 'credit' => 1050.0],
        ], $lines);
        $this->assertTrue(VoucherLines::isBalanced($lines));
    }

    public function test_zero_gst_adds_no_tax_lines(): void
    {
        $this->assertCount(2, VoucherLines::sale(1, 2, 1000, 0, false, self::GST));
        $this->assertCount(2, VoucherLines::purchase(1, 2, 1000, 0, true, self::GST));
    }

    public function test_every_amount_and_rate_produces_balanced_lines(): void
    {
        foreach ([0.01, 0.03, 1.11, 33.33, 100.05, 999.99, 123456.78] as $amount) {
            foreach ([0, 5, 12, 18, 28] as $rate) {
                foreach ([false, true] as $interstate) {
                    $this->assertTrue(VoucherLines::isBalanced(VoucherLines::sale(1, 2, $amount, $rate, $interstate, self::GST)), "sale $amount @ $rate");
                    $this->assertTrue(VoucherLines::isBalanced(VoucherLines::purchase(1, 2, $amount, $rate, $interstate, self::GST)), "purchase $amount @ $rate");
                }
            }
        }
    }

    public function test_balance_check(): void
    {
        $this->assertTrue(VoucherLines::isBalanced([['debit' => 0.1], ['debit' => 0.2], ['credit' => 0.3]]), 'Floating-point dust must not count');
        $this->assertFalse(VoucherLines::isBalanced([['debit' => 100], ['credit' => 99.99]]));
        $this->assertFalse(VoucherLines::isBalanced([['debit' => 0], ['credit' => 0]]), 'An empty voucher is not a voucher');
        $this->assertFalse(VoucherLines::isBalanced([]));
    }

    public function test_balance_is_shown_as_dr_or_cr(): void
    {
        $this->assertSame('12,500.00 Dr', VoucherLines::drCr(12500));
        $this->assertSame('1,00,000.50 Cr', VoucherLines::drCr(-100000.5));
        $this->assertSame('0.00', VoucherLines::drCr(0));
        $this->assertSame('0.00', VoucherLines::drCr(null));
    }
}
