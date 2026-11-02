<?php

namespace Tests\Unit;

use App\Legacy\LegacyDataMapper;
use PHPUnit\Framework\TestCase;

class LegacyDataMapperTest extends TestCase
{
    private LegacyDataMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new LegacyDataMapper();
    }

    public function test_map_report_row_returns_correct_structure(): void
    {
        $row = ['name' => 'US Line', 'reachability_pct' => 95.5, 'country_code' => 'US'];
        $result = $this->mapper->mapReportRow($row);

        $this->assertSame('US Line', $result['label']);
        $this->assertSame(95.5, $result['metric']);
        $this->assertSame('US', $result['region']);
        $this->assertSame('legacy_extract_mapper', $result['source']);
    }

    public function test_map_report_row_injected_key_does_not_affect_output(): void
    {
        // Under the old extract() pattern, passing 'monitors' key could overwrite local variables.
        // Verify this is now isolated.
        $row = [
            'name' => 'Safe Line',
            'reachability_pct' => 99.0,
            'country_code' => 'GB',
            'monitors' => 'INJECTED',
        ];
        $result = $this->mapper->mapReportRow($row);

        $this->assertSame('Safe Line', $result['label']);
        $this->assertSame(99.0, $result['metric']);
        $this->assertSame('GB', $result['region']);
    }

    public function test_map_report_row_uses_defaults_for_missing_keys(): void
    {
        $result = $this->mapper->mapReportRow([]);

        $this->assertSame('Unknown', $result['label']);
        $this->assertSame(0, $result['metric']);
        $this->assertSame('N/A', $result['region']);
    }

    public function test_map_job_context_returns_correct_structure(): void
    {
        $context = ['job_name' => 'Bank IVR', 'phone_number' => '+18005551234', 'menu_depth' => 4];
        $result = $this->mapper->mapJobContext($context);

        $this->assertSame('Bank IVR', $result['job_name']);
        $this->assertSame('+18005551234', $result['phone']);
        $this->assertSame(4, $result['depth']);
    }

    public function test_map_job_context_injected_key_does_not_affect_output(): void
    {
        $context = [
            'job_name' => 'Retail IVR',
            'phone_number' => '+33123456789',
            'menu_depth' => 2,
            'job_name_override' => 'INJECTED',
        ];
        $result = $this->mapper->mapJobContext($context);

        $this->assertSame('Retail IVR', $result['job_name']);
        $this->assertSame('+33123456789', $result['phone']);
    }
}
