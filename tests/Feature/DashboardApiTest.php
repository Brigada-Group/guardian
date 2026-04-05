<?php

namespace Brigada\Guardian\Tests\Feature;

use Brigada\Guardian\Models\RequestLog;
use Brigada\Guardian\Tests\TestCase;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('guardian.dashboard.enabled', true);
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('guardian.monitoring.queries.enabled', false);
    }

    private function authenticatedUser()
    {
        Gate::define('viewGuardianDashboard', fn ($user) => true);

        return new class extends Authenticatable
        {
            public $id = 1;

            protected $guarded = [];
        };
    }

    public function test_overview_api_returns_json(): void
    {
        $response = $this->actingAs($this->authenticatedUser())
            ->getJson('/guardian/api/overview');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta' => ['generated_at', 'next_poll']]);
    }

    public function test_api_requires_authentication(): void
    {
        $response = $this->getJson('/guardian/api/overview');
        $response->assertStatus(403);
    }

    public function test_each_api_endpoint_returns_200(): void
    {
        $user = $this->authenticatedUser();
        $endpoints = [
            'overview', 'requests', 'queries', 'outgoing-http',
            'jobs', 'mail', 'notifications', 'cache', 'exceptions', 'health',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->actingAs($user)->getJson("/guardian/api/{$endpoint}");
            $response->assertStatus(200, "API endpoint {$endpoint} failed");
        }
    }

    public function test_requests_api_returns_data(): void
    {
        RequestLog::create([
            'method' => 'GET',
            'uri' => '/test',
            'status_code' => 200,
            'duration_ms' => 50.0,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->authenticatedUser())
            ->getJson('/guardian/api/requests');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta']);
    }
}
