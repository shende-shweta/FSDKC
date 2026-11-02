<?php

namespace App\Legacy;

class LegacyDataMapper
{
    public function mapReportRow(array $row): array
    {
        $name = $row['name'] ?? 'Unknown';
        $reachabilityPct = $row['reachability_pct'] ?? 0;
        $countryCode = $row['country_code'] ?? 'N/A';

        return [
            'label' => $name,
            'metric' => $reachabilityPct,
            'region' => $countryCode,
            'source' => 'legacy_extract_mapper',
        ];
    }

    public function mapJobContext(array $context): array
    {
        $jobName = $context['job_name'] ?? null;
        $phoneNumber = $context['phone_number'] ?? null;
        $menuDepth = $context['menu_depth'] ?? 0;

        return [
            'job_name' => $jobName,
            'phone' => $phoneNumber,
            'depth' => $menuDepth,
        ];
    }
}
