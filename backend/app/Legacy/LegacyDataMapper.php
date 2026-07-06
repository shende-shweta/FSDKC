<?php

namespace App\Legacy;

/**
 * Legacy data mapper — safe direct array access (AC-A03).
 *
 * Removed extract() calls:
 *   - mapReportRow() previously called extract($row, EXTR_SKIP)
 *   - mapJobContext() previously called extract($context) with no flag
 *
 * Both methods now use explicit array key access with null-coalescing defaults.
 * The return shape is identical to the previous implementation.
 */
class LegacyDataMapper
{
    /**
     * Map a flat report row array to the canonical report shape.
     *
     * @param  array<string, mixed>  $row
     * @return array{label: string, metric: float|int, region: string, source: string}
     */
    public function mapReportRow(array $row): array
    {
        return [
            'label'  => (string) ($row['name'] ?? 'Unknown'),
            'metric' => $row['reachability_pct'] ?? 0,
            'region' => (string) ($row['country_code'] ?? 'N/A'),
            'source' => 'legacy_mapper',
        ];
    }

    /**
     * Map a job context array to the canonical context shape.
     *
     * @param  array<string, mixed>  $context
     * @return array{job_name: string|null, phone: string|null, depth: int}
     */
    public function mapJobContext(array $context): array
    {
        return [
            'job_name' => isset($context['job_name']) ? (string) $context['job_name'] : null,
            'phone'    => isset($context['phone_number']) ? (string) $context['phone_number'] : null,
            'depth'    => (int) ($context['menu_depth'] ?? 0),
        ];
    }
}
