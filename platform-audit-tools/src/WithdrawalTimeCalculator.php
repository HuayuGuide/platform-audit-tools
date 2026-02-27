<?php

declare(strict_types=1);

namespace HuayuGuide\AuditTools;

/**
 * WithdrawalTimeCalculator
 *
 * Standalone utility for calculating and classifying online platform
 * withdrawal processing speed. Used by HuayuGuide.com's audit system
 * to generate human-readable labels and risk tags from raw minute values.
 *
 * Design principles:
 * - Zero external dependencies
 * - All inputs validated defensively (no silent failures)
 * - Thresholds are runtime-configurable, not hard-coded
 * - Output structured for direct consumption by Schema.org generators
 *
 * @package HuayuGuide\AuditTools
 * @link    https://huayuguide.com/audit/
 * @license MIT
 */
final class WithdrawalTimeCalculator
{
    /**
     * Default speed thresholds (in minutes).
     * Override via $config parameter in evaluateSpeed().
     */
    public const DEFAULT_THRESHOLDS = [
        'instant' => 5,    // ≤ 5 min  → 秒级出款
        'fast'    => 30,   // ≤ 30 min → 快速出款
        'slow'    => 240,  // ≤ 240 min → 出款时效正常; > 240 → 出款偏慢
    ];

    /**
     * Convert raw minutes into a human-readable Chinese duration string.
     *
     * Examples:
     *   0.3   → "秒级"
     *   7.5   → "7.5分钟"
     *   90.0  → "1.5小时"
     *   0.0   → "秒级"
     *
     * @param  float|null $mins Raw duration in minutes. Null or negative returns ''.
     * @return string           Formatted duration string, empty string on invalid input.
     */
    public function formatDuration(?float $mins): string
    {
        if ($mins === null || !is_finite($mins) || $mins < 0) {
            return '';
        }

        if ($mins < 1) {
            return '秒级';
        }

        if ($mins >= 60) {
            $hours = $mins / 60;
            $formatted = rtrim(rtrim(number_format($hours, 1, '.', ''), '0'), '.');
            return ($formatted === '' ? '1' : $formatted) . '小时';
        }

        $formatted = rtrim(rtrim(number_format($mins, 1, '.', ''), '0'), '.');
        return ($formatted === '' ? '1' : $formatted) . '分钟';
    }

    /**
     * Classify withdrawal speed against configurable thresholds.
     *
     * Returns a structured result with:
     * - code:  machine-readable speed category
     * - label: human-readable Chinese label
     * - score: integer score for ranking (-1 = unknown, -2 = slow, 0 = normal, 1 = fast, 2 = instant)
     * - tags:  array of display tags (used for Schema.org additionalProperty)
     *
     * @param  float|null $durationMin Processing time in minutes.
     * @param  array      $config      Threshold config. Keys: 'instant', 'fast', 'slow' (all in minutes).
     * @return array{code:string, label:string, score:int, tags:string[]}
     *
     * @example
     * $result = $calc->evaluateSpeed(8.0, ['instant'=>5,'fast'=>30,'slow'=>240]);
     * // ['code'=>'fast','label'=>'快速出款','score'=>1,'tags'=>['快速出款']]
     */
    public function evaluateSpeed(?float $durationMin, array $config = []): array
    {
        // Merge with defaults
        $thresholds = array_merge(self::DEFAULT_THRESHOLDS, $config);

        // Defensive: validate input
        if ($durationMin === null || !is_finite($durationMin) || $durationMin < 0) {
            return [
                'code'  => 'unknown',
                'label' => '耗时数据缺失',
                'score' => -1,
                'tags'  => ['耗时数据缺失'],
            ];
        }

        $instant = (float)$thresholds['instant'];
        $fast    = (float)$thresholds['fast'];
        $slow    = (float)$thresholds['slow'];

        if ($durationMin <= $instant) {
            return [
                'code'  => 'instant',
                'label' => '秒级出款',
                'score' => 2,
                'tags'  => ['秒级出款'],
            ];
        }

        if ($durationMin <= $fast) {
            return [
                'code'  => 'fast',
                'label' => '快速出款',
                'score' => 1,
                'tags'  => ['快速出款'],
            ];
        }

        if ($durationMin <= $slow) {
            return [
                'code'  => 'normal',
                'label' => '出款时效正常',
                'score' => 0,
                'tags'  => ['出款时效正常'],
            ];
        }

        return [
            'code'  => 'slow',
            'label' => '出款偏慢',
            'score' => -2,
            'tags'  => ['出款偏慢'],
        ];
    }

    /**
     * Calculate processing time in minutes from two Unix timestamps.
     *
     * Defensive: returns null if end_time < start_time (negative duration guard).
     *
     * @param  int   $startTimestamp Unix timestamp of withdrawal submission
     * @param  int   $endTimestamp   Unix timestamp of funds credited
     * @return float|null            Duration in minutes, or null on invalid input
     */
    public function calculateDurationFromTimestamps(int $startTimestamp, int $endTimestamp): ?float
    {
        if ($endTimestamp < $startTimestamp) {
            // Guard: negative duration is a data entry error, not a valid result
            return null;
        }

        $diffSeconds = $endTimestamp - $startTimestamp;
        return round($diffSeconds / 60, 2);
    }

    /**
     * Validate a duration value for admin-side data entry.
     * Returns an error message string, or empty string if valid.
     *
     * Used to surface data entry errors before they pollute audit records.
     *
     * @param  mixed $value
     * @return string  Error message, or '' if valid
     */
    public function validateDurationInput($value): string
    {
        if ($value === null || $value === '') {
            return ''; // Optional field, null is acceptable
        }

        if (!is_numeric($value)) {
            return '提款耗时必须为数字（分钟）';
        }

        $float = (float)$value;

        if ($float < 0) {
            return '提款耗时不能为负数，请检查填写的时间';
        }

        if ($float > 43200) { // 30 days in minutes
            return '提款耗时超过30天，请核实数据是否正确';
        }

        return '';
    }
}
