<?php

namespace App\Legacy;

class LegacyDataMapper
{
    public function mapReportRow(array $row): array
    {
        return [
            'label' => $row['name'] ?? 'Unknown',
            'metric' => $row['reachability_pct'] ?? 0,
            'region' => $row['country_code'] ?? 'N/A',
            'source' => 'legacy_mapper',
        ];
    }

    public function mapJobContext(array $context): array
    {
        return [
            'job_name' => $context['job_name'] ?? null,
            'phone' => $context['phone_number'] ?? null,
            'depth' => $context['menu_depth'] ?? 0,
        ];
    }
}
