<?php

declare(strict_types=1);

namespace HuayuGuide\AuditTools;

/**
 * FxLossCalculator
 *
 * Calculates hidden foreign exchange losses in online platform withdrawals.
 *
 * Background:
 * Many platforms apply undisclosed exchange rates that deviate significantly
 * from market mid-rates, effectively extracting an additional fee. This
 * calculator surfaces those losses as a percentage deviation.
 *
 * Used by HuayuGuide.com to provide transparent FX loss data in platform
 * audit records. See: https://huayuguide.com/audit/
 *
 * Supported currency pairs (common in Southeast Asian markets):
 * - CNY, MYR, HKD (fiat)
 * - USDT, BTC, ETH (crypto, treated as USD-pegged for USDT)
 *
 * @package HuayuGuide\AuditTools
 * @link    https://huayuguide.com/audit/
 * @license MIT
 */
final class FxLossCalculator
{
    /**
     * Default threshold: loss percentage above which "severe_loss" flag is set.
     * Configurable per instance.
     */
    private float $severeLossThreshold;

    public function __construct(float $severeLossThreshold = 2.0)
    {
        $this->severeLossThreshold = $severeLossThreshold;
    }

    /**
     * Analyze same-currency withdrawal loss.
     *
     * Detects platform-side fees and shortfalls even without currency conversion.
     *
     * @param  float  $amountApply     Amount requested for withdrawal
     * @param  string $currency        Currency code (e.g., 'USDT', 'CNY')
     * @param  float  $amountReceived  Amount actually credited
     * @return array{
     *   loss_amount: float|null,
     *   loss_pct: float|null,
     *   deviation_pct: null,
     *   severe_loss: bool,
     *   has_cross_currency: false,
     *   error: string|null
     * }
     */
    public function analyze(float $amountApply, string $currency, float $amountReceived): array
    {
        $empty = $this->emptyResult();

        if ($amountApply <= 0) {
            return array_merge($empty, ['error' => 'apply_amount_invalid']);
        }

        // Guard: received > applied is a data entry error
        if ($amountReceived > $amountApply * 1.05) {
            return array_merge($empty, ['error' => 'received_exceeds_applied']);
        }

        $lossAmount = round($amountApply - $amountReceived, 8);
        $lossPct    = round(($lossAmount / $amountApply) * 100, 4);

        return [
            'loss_amount'       => $lossAmount,
            'loss_pct'          => $lossPct,
            'deviation_pct'     => null,
            'severe_loss'       => $lossPct > $this->severeLossThreshold,
            'has_cross_currency' => false,
            'currency'          => strtoupper($currency),
            'error'             => null,
        ];
    }

    /**
     * Analyze cross-currency withdrawal loss using a reference exchange rate.
     *
     * The "hidden loss" is the deviation between:
     * - What the user *should* receive at market mid-rate
     * - What they *actually* received
     *
     * @param  float  $amountApply      Amount requested (in source currency)
     * @param  string $currencyApply    Source currency code (e.g., 'CNY')
     * @param  float  $amountReceived   Amount actually credited (in target currency)
     * @param  string $currencyReceived Target currency code (e.g., 'MYR')
     * @param  float  $referenceRate    Market mid-rate: 1 unit of $currencyApply = ? $currencyReceived
     * @return array{
     *   loss_amount: float|null,
     *   loss_pct: float|null,
     *   deviation_pct: float|null,
     *   expected_amount: float,
     *   severe_loss: bool,
     *   has_cross_currency: true,
     *   error: string|null
     * }
     */
    public function analyzeCross(
        float $amountApply,
        string $currencyApply,
        float $amountReceived,
        string $currencyReceived,
        float $referenceRate
    ): array {
        $empty = array_merge($this->emptyResult(), ['has_cross_currency' => true]);

        if ($amountApply <= 0) {
            return array_merge($empty, ['error' => 'apply_amount_invalid']);
        }

        if ($referenceRate <= 0) {
            return array_merge($empty, ['error' => 'reference_rate_invalid']);
        }

        // What the user *should* have received at fair market rate
        $expectedAmount = round($amountApply * $referenceRate, 8);

        if ($expectedAmount <= 0) {
            return array_merge($empty, ['error' => 'expected_amount_zero']);
        }

        // Guard: received > 105% of expected is likely a data error
        if ($amountReceived > $expectedAmount * 1.05) {
            return array_merge($empty, ['error' => 'received_exceeds_expected']);
        }

        $lossAmount   = round($expectedAmount - $amountReceived, 8);
        $deviationPct = round(($lossAmount / $expectedAmount) * 100, 4);

        // For display purposes, also compute loss in source currency terms
        $lossPct = round(($lossAmount / $expectedAmount) * 100, 4);

        return [
            'loss_amount'        => $lossAmount,
            'loss_pct'           => $lossPct,
            'deviation_pct'      => $deviationPct,
            'expected_amount'    => $expectedAmount,
            'severe_loss'        => $deviationPct > $this->severeLossThreshold,
            'has_cross_currency' => true,
            'currency_apply'     => strtoupper($currencyApply),
            'currency_received'  => strtoupper($currencyReceived),
            'reference_rate'     => $referenceRate,
            'error'              => null,
        ];
    }

    /**
     * Classify FX loss severity into a labeled result for display.
     *
     * @param  float|null $deviationPct  Deviation percentage (use loss_pct for same-currency)
     * @param  array      $config        Thresholds: ['normal' => 0.5, 'warn' => 2.0]
     * @return array{code:string, label:string, score:int, tags:string[]}
     */
    public function classifyLoss(?float $deviationPct, array $config = []): array
    {
        $normalThreshold = (float)($config['normal'] ?? 0.5);
        $warnThreshold   = (float)($config['warn']   ?? 2.0);

        if ($deviationPct === null || !is_finite($deviationPct)) {
            return [
                'code'  => 'unknown',
                'label' => '汇损数据缺失',
                'score' => -1,
                'tags'  => ['汇损数据缺失'],
            ];
        }

        if ($deviationPct <= 0) {
            return [
                'code'  => 'zero_loss',
                'label' => '无汇损',
                'score' => 2,
                'tags'  => ['无汇损'],
            ];
        }

        if ($deviationPct <= $normalThreshold) {
            return [
                'code'  => 'minimal',
                'label' => '汇损极低',
                'score' => 1,
                'tags'  => ['汇损极低'],
            ];
        }

        if ($deviationPct <= $warnThreshold) {
            return [
                'code'  => 'moderate',
                'label' => '存在汇损',
                'score' => 0,
                'tags'  => ['存在汇损'],
            ];
        }

        return [
            'code'  => 'severe',
            'label' => '汇损严重',
            'score' => -2,
            'tags'  => ['汇损严重'],
        ];
    }

    /**
     * @return array
     */
    private function emptyResult(): array
    {
        return [
            'loss_amount'        => null,
            'loss_pct'           => null,
            'deviation_pct'      => null,
            'severe_loss'        => false,
            'has_cross_currency' => false,
            'error'              => null,
        ];
    }
}
