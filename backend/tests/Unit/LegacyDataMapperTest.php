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

    public function test_map_report_row_uses_provided_fields(): void
    {
        $result = $this->mapper->mapReportRow([
            'name' => 'US Sales TFN',
            'reachability_pct' => 99.5,
            'country_code' => 'US',
        ]);

        $this->assertSame('US Sales TFN', $result['label']);
        $this->assertSame(99.5, $result['metric']);
        $this->assertSame('US', $result['region']);
        $this->assertSame('legacy_mapper', $result['source']);
    }

    public function test_map_report_row_defaults_missing_fields(): void
    {
        $result = $this->mapper->mapReportRow([]);

        $this->assertSame('Unknown', $result['label']);
        $this->assertSame(0, $result['metric']);
        $this->assertSame('N/A', $result['region']);
    }

    public function test_map_job_context_uses_provided_fields(): void
    {
        $result = $this->mapper->mapJobContext([
            'job_name' => 'Bank IVR',
            'phone_number' => '+18005551234',
            'menu_depth' => 3,
        ]);

        $this->assertSame('Bank IVR', $result['job_name']);
        $this->assertSame('+18005551234', $result['phone']);
        $this->assertSame(3, $result['depth']);
    }

    public function test_map_job_context_defaults_missing_fields(): void
    {
        $result = $this->mapper->mapJobContext([]);

        $this->assertNull($result['job_name']);
        $this->assertNull($result['phone']);
        $this->assertSame(0, $result['depth']);
    }

    public function test_map_report_row_does_not_pollute_scope(): void
    {
        // Ensure no extract()-style variable injection can occur.
        // Passing a key that matches a PHP superglobal name should be harmless.
        $result = $this->mapper->mapReportRow([
            'name' => 'Injected',
            'GLOBALS' => 'overwrite_attempt',
            'reachability_pct' => 50.0,
            'country_code' => 'GB',
        ]);

        $this->assertSame('Injected', $result['label']);
        $this->assertSame(50.0, $result['metric']);
    }
}
