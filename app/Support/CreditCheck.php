<?php

namespace App\Support;

/**
 * Would this order take the customer over their credit limit? Exposure is what their ledger says
 * they owe (debit positive; an advance reduces it) plus this order. No limit means no check.
 */
class CreditCheck
{
    /**
     * @return array{limited: bool, exposure: float, over_by: float, exceeded: bool}
     */
    public static function evaluate(float|string|null $limit, float|string|null $ledgerBalance, float|string|null $orderTotal): array
    {
        $exposure = round((float) $ledgerBalance + (float) $orderTotal, 2);
        $limited = (float) $limit > 0;
        $overBy = $limited ? round(max($exposure - (float) $limit, 0), 2) : 0.0;

        return ['limited' => $limited, 'exposure' => $exposure, 'over_by' => $overBy, 'exceeded' => $overBy > 0];
    }
}
