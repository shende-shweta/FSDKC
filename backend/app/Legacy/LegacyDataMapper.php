<?php

namespace App\Legacy;

use App\Legacy\Contracts\DataMapperInterface;

/**
 * Legacy data mapper — direct array key access (no extract()).
 * AC-A03: Remove extract() variable-injection anti-pattern.
 */
class LegacyDataMapper
{
    /**
     * Maps a legacy report row to a standardized format.
     *
     * @param array $row  Raw row with optional name, reachability_pct, country_code
     * @return array      Standardized report row
     */
    public function mapReportRow(array $row): array
    {
        return [
            'label'  => isset($row['name']) && is_string($row['name']) ? $row['name'] : 'Unknown',
            'metric' => isset($row['reachability_pct']) ? (float) $row['reachability_pct'] : 0,
            'region' => isset($row['country_code']) && is_string($row['country_code']) ? $row['country_code'] : 'N/A',
            'source' => 'legacy_mapper',
        ];
    }

    /**
     * Maps a legacy job context object.
     *
     * @param array $context  Raw context with optional job_name, phone_number, menu_depth
     * @return array          Standardized job context
     */
    public function mapJobContext(array $context): array
    {
        return [
            'job_name' => isset($context['job_name']) && is_string($context['job_name']) ? $context['job_name'] : null,
            'phone'    => isset($context['phone_number']) && is_string($context['phone_number']) ? $context['phone_number'] : null,
            'depth'    => isset($context['menu_depth']) ? (int) $context['menu_depth'] : 0,
        ];
    }
}
