<?php

namespace Tests\Unit\Sales;

use App\Support\BuyerScore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BuyerScoreTest extends TestCase
{
    protected function best(): array
    {
        return [
            'buyer_type' => 'brand_owner', 'monthly_volume' => 500000, 'price_fit' => 'at_or_above', 'mould_fit' => 'existing',
            'decision_authority' => 'decision_maker', 'purchase_timeline' => 'immediate', 'payment_terms' => 'advance',
        ];
    }

    public function test_the_best_possible_buyer_scores_exactly_100(): void
    {
        $this->assertSame(100, BuyerScore::score($this->best()));
    }

    public function test_every_criterion_best_answer_adds_up_to_100(): void
    {
        $max = max(BuyerScore::VOLUME_BANDS);
        foreach (BuyerScore::CRITERIA as $criterion) {
            $max += max(array_column($criterion['options'], 1));
        }

        $this->assertSame(100, $max);
    }

    public function test_a_realistic_buyer_is_the_sum_of_their_answers(): void
    {
        $score = BuyerScore::score([
            'buyer_type' => 'distributor',       // 10
            'monthly_volume' => 30000,           // 20
            'price_fit' => 'negotiable',         // 10
            'mould_fit' => 'new_buyer_funded',   // 14
            'decision_authority' => 'influencer', // 3
            'purchase_timeline' => 'one_to_three', // 7
            'payment_terms' => 'credit_30',      // 6
        ]);

        $this->assertSame(70, $score);
    }

    public function test_missing_or_unknown_answers_earn_nothing(): void
    {
        $this->assertSame(2, BuyerScore::score([]));
        $this->assertSame(2, BuyerScore::score(['buyer_type' => 'alien', 'monthly_volume' => 10]));
    }

    #[DataProvider('volumes')]
    public function test_volume_bands(int|float|null $volume, int $points): void
    {
        $this->assertSame($points, BuyerScore::volumePoints($volume));
    }

    public static function volumes(): array
    {
        return [
            'nothing' => [0, 2], 'null' => [null, 2], 'just below 1k' => [999, 2], 'exactly 1k' => [1000, 6],
            'just below 5k' => [4999, 6], 'exactly 5k' => [5000, 12], 'exactly 25k' => [25000, 20],
            'just below 1 lakh' => [99999, 20], 'exactly 1 lakh' => [100000, 25], 'huge' => [50000000, 25],
        ];
    }

    #[DataProvider('grades')]
    public function test_grade_boundaries(int $score, string $grade): void
    {
        $this->assertSame($grade, BuyerScore::grade($score));
    }

    public static function grades(): array
    {
        return [[100, 'A'], [75, 'A'], [74, 'B'], [50, 'B'], [49, 'C'], [25, 'C'], [24, 'D'], [0, 'D']];
    }

    public function test_recommendation_follows_the_grade(): void
    {
        $this->assertSame('qualified', BuyerScore::recommendation(80));
        $this->assertSame('qualified', BuyerScore::recommendation(50));
        $this->assertSame('nurture', BuyerScore::recommendation(30));
        $this->assertSame('disqualified', BuyerScore::recommendation(10));
    }

    public function test_a_product_we_cannot_make_is_never_recommended_however_high_the_score(): void
    {
        $this->assertSame('disqualified', BuyerScore::recommendation(100, 'not_feasible'));
    }

    public function test_labels_and_options(): void
    {
        $this->assertSame('Trader', BuyerScore::label('buyer_type', 'trader'));
        $this->assertNull(BuyerScore::label('buyer_type', 'nope'));
        $this->assertArrayHasKey('credit_60', BuyerScore::options('payment_terms'));
        $this->assertSame('60+ days credit', BuyerScore::options('payment_terms')['credit_60']);
    }
}
