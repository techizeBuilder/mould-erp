<?php

namespace Tests\Unit\Sales;

use App\Support\RetentionHealth;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RetentionHealthTest extends TestCase
{
    #[DataProvider('states')]
    public function test_state_thresholds_for_a_30_day_cycle(int $days, string $state): void
    {
        $this->assertSame($state, RetentionHealth::state($days, 30));
    }

    public static function states(): array
    {
        return [
            'just ordered' => [0, 'healthy'], 'last healthy day' => [29, 'healthy'], 'cycle reached' => [30, 'due'],
            'last due day' => [44, 'due'], 'at risk starts' => [45, 'at_risk'], 'last at-risk day' => [89, 'at_risk'],
            'lapsed starts' => [90, 'lost'], 'long gone' => [400, 'lost'],
        ];
    }

    public function test_average_gap_needs_two_distinct_dates(): void
    {
        $this->assertNull(RetentionHealth::averageGap([]));
        $this->assertNull(RetentionHealth::averageGap(['2026-01-01']));
        $this->assertNull(RetentionHealth::averageGap(['2026-01-01', '2026-01-01']));
    }

    public function test_average_gap_ignores_order_and_same_day_duplicates(): void
    {
        // Two products on the same PO count as one order date.
        $this->assertSame(30, RetentionHealth::averageGap(['2026-03-02', '2026-01-01', '2026-01-31', '2026-01-31']));
    }

    public function test_a_customer_with_no_orders_is_new(): void
    {
        $result = RetentionHealth::evaluate([], null, Carbon::parse('2026-06-01'));

        $this->assertSame('new', $result['health']);
        $this->assertNull($result['last_order']);
        $this->assertNull($result['next_expected']);
        $this->assertSame(RetentionHealth::DEFAULT_CYCLE_DAYS, $result['cycle']);
    }

    public function test_one_order_uses_the_default_cycle(): void
    {
        $result = RetentionHealth::evaluate(['2026-05-01'], null, Carbon::parse('2026-05-11'));

        $this->assertSame('healthy', $result['health']);
        $this->assertSame(10, $result['days_since']);
        $this->assertSame('2026-06-15', $result['next_expected']->toDateString());
    }

    public function test_repeat_orders_use_their_own_rhythm(): void
    {
        $result = RetentionHealth::evaluate(['2026-01-01', '2026-01-21', '2026-02-10'], null, Carbon::parse('2026-03-05'));

        $this->assertSame(20, $result['average_gap']);
        $this->assertSame(20, $result['cycle']);
        $this->assertSame('2026-03-02', $result['next_expected']->toDateString());
        $this->assertSame(23, $result['days_since']);
        $this->assertSame('due', $result['health']);
    }

    public function test_a_cycle_set_on_the_customer_beats_their_average(): void
    {
        $result = RetentionHealth::evaluate(['2026-01-01', '2026-01-21'], 90, Carbon::parse('2026-02-20'));

        $this->assertSame(90, $result['cycle']);
        $this->assertSame(20, $result['average_gap']);
        $this->assertSame('healthy', $result['health']);
    }

    public function test_time_of_day_does_not_change_the_day_count(): void
    {
        $result = RetentionHealth::evaluate([Carbon::parse('2026-05-01 23:30')], 30, Carbon::parse('2026-05-31 00:10'));

        $this->assertSame(30, $result['days_since']);
        $this->assertSame('due', $result['health']);
    }

    public function test_labels_and_tones(): void
    {
        $this->assertSame('At risk', RetentionHealth::label('at_risk'));
        $this->assertSame('Lapsed', RetentionHealth::label('lost'));
        $this->assertStringContainsString('red', RetentionHealth::tone('lost'));
        $this->assertStringContainsString('slate', RetentionHealth::tone('nonsense'));
    }
}
