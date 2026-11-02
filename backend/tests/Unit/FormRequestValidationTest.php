<?php

namespace Tests\Unit;

use App\Http\Requests\ConnectRequest;
use App\Http\Requests\DiscoveryRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

// AC: T-15, T-16 — FormRequest rule coverage: phone, country_code, language enum
class FormRequestValidationTest extends TestCase
{
    // ── DiscoveryRequest ──────────────────────────────────────────────────────

    private function discoveryValidator(array $data): \Illuminate\Validation\Validator
    {
        $request = new DiscoveryRequest();
        return Validator::make($data, $request->rules());
    }

    public function test_discovery_accepts_valid_e164_phone_number(): void
    {
        $v = $this->discoveryValidator(['name' => 'Test', 'phone_number' => '+18005551234', 'country_code' => 'US']);
        $this->assertFalse($v->fails(), implode(', ', $v->errors()->all()));
    }

    public function test_discovery_accepts_phone_without_leading_plus(): void
    {
        $v = $this->discoveryValidator(['name' => 'Test', 'phone_number' => '18005551234', 'country_code' => 'US']);
        $this->assertFalse($v->fails());
    }

    public function test_discovery_rejects_too_short_phone_number(): void
    {
        $v = $this->discoveryValidator(['name' => 'Test', 'phone_number' => '123', 'country_code' => 'US']);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('phone_number', $v->errors()->toArray());
    }

    public function test_discovery_rejects_phone_with_letters(): void
    {
        $v = $this->discoveryValidator(['name' => 'Test', 'phone_number' => '+1800abc1234', 'country_code' => 'US']);
        $this->assertTrue($v->fails());
    }

    public function test_discovery_accepts_allowed_country_code(): void
    {
        foreach (['US', 'GB', 'FR', 'DE', 'IN'] as $cc) {
            $v = $this->discoveryValidator(['name' => 'T', 'phone_number' => '+18005551234', 'country_code' => $cc]);
            $this->assertFalse($v->fails(), "Expected {$cc} to be valid");
        }
    }

    public function test_discovery_rejects_unknown_country_code(): void
    {
        $v = $this->discoveryValidator(['name' => 'Test', 'phone_number' => '+18005551234', 'country_code' => 'XX']);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('country_code', $v->errors()->toArray());
    }

    public function test_discovery_rejects_unknown_language(): void
    {
        $v = $this->discoveryValidator(['name' => 'Test', 'phone_number' => '+18005551234', 'country_code' => 'US', 'languages' => ['xx']]);
        $this->assertTrue($v->fails());
    }

    public function test_discovery_accepts_all_allowed_languages(): void
    {
        foreach (['en', 'fr', 'de', 'es', 'pt', 'zh', 'ar', 'ja', 'ko', 'hi'] as $lang) {
            $v = $this->discoveryValidator(['name' => 'T', 'phone_number' => '+18005551234', 'country_code' => 'US', 'languages' => [$lang]]);
            $this->assertFalse($v->fails(), "Expected language '{$lang}' to be valid");
        }
    }

    public function test_discovery_rejects_missing_name(): void
    {
        $v = $this->discoveryValidator(['phone_number' => '+18005551234', 'country_code' => 'US']);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('name', $v->errors()->toArray());
    }

    // ── ConnectRequest ────────────────────────────────────────────────────────

    private function connectValidator(array $data): \Illuminate\Validation\Validator
    {
        $request = new ConnectRequest();
        return Validator::make($data, $request->rules());
    }

    public function test_connect_accepts_valid_toll_free_number(): void
    {
        $v = $this->connectValidator(['name' => 'US TFN', 'toll_free_number' => '18005551234', 'country_code' => 'US']);
        $this->assertFalse($v->fails());
    }

    public function test_connect_accepts_toll_free_with_plus_prefix(): void
    {
        $v = $this->connectValidator(['name' => 'US TFN', 'toll_free_number' => '+18005551234', 'country_code' => 'US']);
        $this->assertFalse($v->fails());
    }

    public function test_connect_rejects_too_short_toll_free_number(): void
    {
        $v = $this->connectValidator(['name' => 'US TFN', 'toll_free_number' => '123', 'country_code' => 'US']);
        $this->assertTrue($v->fails());
    }

    public function test_connect_rejects_unknown_country_code(): void
    {
        $v = $this->connectValidator(['name' => 'Bad CC', 'toll_free_number' => '18005551234', 'country_code' => 'ZZ']);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('country_code', $v->errors()->toArray());
    }

    public function test_connect_carrier_is_optional(): void
    {
        $v = $this->connectValidator(['name' => 'No Carrier', 'toll_free_number' => '18005551234', 'country_code' => 'US']);
        $this->assertFalse($v->fails());
    }

    public function test_connect_rejects_missing_name(): void
    {
        $v = $this->connectValidator(['toll_free_number' => '18005551234', 'country_code' => 'US']);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('name', $v->errors()->toArray());
    }
}
