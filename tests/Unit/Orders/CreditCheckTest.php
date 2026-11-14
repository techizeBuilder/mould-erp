<?php

namespace Tests\Unit\Orders;

use App\Support\CreditCheck;
use PHPUnit\Framework\TestCase;

class CreditCheckTest extends TestCase
{
    public function test_no_limit_means_nothing_is_exceeded(): void
    {
        foreach ([null, 0, '0.00', ''] as $limit) {
            $result = CreditCheck::evaluate($limit, 900000, 900000);

            $this->assertFalse($result['limited']);
            $this->assertFalse($result['exceeded']);
            $this->assertSame(0.0, $result['over_by']);
            $this->assertSame(1800000.0, $result['exposure']);
        }
    }

    public function test_within_the_limit_and_exactly_on_it_are_fine(): void
    {
        $this->assertFalse(CreditCheck::evaluate(100000, 40000, 50000)['exceeded']);
        $this->assertFalse(CreditCheck::evaluate(100000, 50000, 50000)['exceeded']);
    }

    public function test_one_paisa_over_counts(): void
    {
        $result = CreditCheck::evaluate(100000, 50000, 50000.01);

        $this->assertTrue($result['exceeded']);
        $this->assertSame(0.01, $result['over_by']);
    }

    public function test_an_advance_in_the_ledger_frees_up_credit(): void
    {
        $result = CreditCheck::evaluate(100000, -30000, 120000);

        $this->assertSame(90000.0, $result['exposure']);
        $this->assertFalse($result['exceeded']);
    }

    public function test_decimal_strings_from_the_database_work(): void
    {
        $result = CreditCheck::evaluate('100000.00', '75000.50', '30000.25');

        $this->assertSame(105000.75, $result['exposure']);
        $this->assertSame(5000.75, $result['over_by']);
    }
}
