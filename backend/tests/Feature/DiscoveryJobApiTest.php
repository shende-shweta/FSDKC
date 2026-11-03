<?php

namespace Tests\Feature;

use App\Models\DiscoveryJob;
use App\Models\DiscoveryNode;
use Tests\TestCase;

class DiscoveryJobApiTest extends TestCase
{
    public function test_index_returns_jobs(): void
    {
        DiscoveryJob::factory()->count(2)->create();

        $response = $this->getJson('/api/discovery/jobs');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_store_creates_job_with_valid_data(): void
    {
        $payload = [
            'name' => 'Bank IVR Discovery',
            'phone_number' => '+18005551234',
            'country_code' => 'US',
            'languages' => ['en'],
        ];

        $response = $this->postJson('/api/discovery/jobs', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Bank IVR Discovery')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.languages', ['en']);

        $this->assertDatabaseHas('discovery_jobs', ['name' => 'Bank IVR Discovery']);
    }

    public function test_store_rejects_non_e164_phone(): void
    {
        $response = $this->postJson('/api/discovery/jobs', [
            'name' => 'Test',
            'phone_number' => '18005551234',  // Missing leading +
            'country_code' => 'US',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone_number']);
    }

    public function test_store_rejects_invalid_country_code(): void
    {
        $response = $this->postJson('/api/discovery/jobs', [
            'name' => 'Test',
            'phone_number' => '+18005551234',
            'country_code' => 'usa',  // Lowercase — should fail
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['country_code']);
    }

    public function test_store_rejects_too_many_languages(): void
    {
        $response = $this->postJson('/api/discovery/jobs', [
            'name' => 'Test',
            'phone_number' => '+18005551234',
            'country_code' => 'US',
            'languages' => array_fill(0, 11, 'en'),  // 11 items — max is 10
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['languages']);
    }

    public function test_store_defaults_languages_to_en(): void
    {
        $response = $this->postJson('/api/discovery/jobs', [
            'name' => 'Test',
            'phone_number' => '+18005551234',
            'country_code' => 'US',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.languages', ['en']);
    }

    public function test_show_returns_job(): void
    {
        $job = DiscoveryJob::factory()->create();

        $response = $this->getJson("/api/discovery/jobs/{$job->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $job->id);
    }

    public function test_show_returns_404_for_missing_job(): void
    {
        $this->getJson('/api/discovery/jobs/99999')
            ->assertNotFound();
    }

    public function test_tree_returns_nested_structure(): void
    {
        $job = DiscoveryJob::factory()->create();

        $root = DiscoveryNode::factory()->create([
            'discovery_job_id' => $job->id,
            'parent_id' => null,
            'depth' => 0,
        ]);
        DiscoveryNode::factory()->create([
            'discovery_job_id' => $job->id,
            'parent_id' => $root->id,
            'depth' => 1,
        ]);

        $response = $this->getJson("/api/discovery/jobs/{$job->id}/tree");

        $response->assertOk()
            ->assertJsonPath('job_id', $job->id)
            ->assertJsonCount(1, 'tree')
            ->assertJsonCount(1, 'tree.0.children');
    }

    public function test_start_returns_409_when_already_running(): void
    {
        $job = DiscoveryJob::factory()->create(['status' => 'running']);

        $this->postJson("/api/discovery/jobs/{$job->id}/start")
            ->assertStatus(409);
    }

    public function test_start_returns_session_id_for_pending_job(): void
    {
        // AC-04: start endpoint queues async job and immediately returns session_id
        $job = DiscoveryJob::factory()->create(['status' => 'pending']);

        $response = $this->postJson("/api/discovery/jobs/{$job->id}/start");

        $response->assertOk()
            ->assertJsonStructure(['session_id', 'message']);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $response->json('session_id')
        );
    }

    public function test_start_returns_404_for_missing_job(): void
    {
        $this->postJson('/api/discovery/jobs/99999/start')
            ->assertNotFound();
    }

    public function test_store_rejects_invalid_bcp47_language_item(): void
    {
        // AC-09: languages.* must match /^[a-z]{2}(-[A-Z]{2})?$/ — 'xyz' exceeds 2-char code
        $response = $this->postJson('/api/discovery/jobs', [
            'name' => 'Test',
            'phone_number' => '+18005551234',
            'country_code' => 'US',
            'languages' => ['xyz'],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['languages.0']);
    }

    public function test_store_accepts_locale_style_language_code(): void
    {
        // AC-09: en-US and fr-CA are valid BCP-47 codes
        $response = $this->postJson('/api/discovery/jobs', [
            'name' => 'Locale Language Test',
            'phone_number' => '+18005551234',
            'country_code' => 'US',
            'languages' => ['en-US', 'fr-CA'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.languages', ['en-US', 'fr-CA']);
    }

    public function test_store_rejects_numeric_language_item(): void
    {
        // AC-09: array items must be strings, not integers
        $response = $this->postJson('/api/discovery/jobs', [
            'name' => 'Numeric Lang',
            'phone_number' => '+18005551234',
            'country_code' => 'US',
            'languages' => [42],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['languages.0']);
    }
}
