<?php

namespace Brigada\Guardian\Tests\Feature;

use Brigada\Guardian\Tests\TestCase;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('guardian.dashboard.enabled', true);
        $app['config']->set('guardian.dashboard.path', 'guardian');
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('guardian.monitoring.queries.enabled', false);
    }

    public function test_unauthenticated_user_gets_403(): void
    {
        $response = $this->get('/guardian');
        $response->assertStatus(403);
    }

    public function test_authorized_user_can_access_dashboard(): void
    {
        Gate::define('viewGuardianDashboard', fn ($user) => true);

        $user = new class extends Authenticatable
        {
            public $id = 1;

            protected $guarded = [];
        };

        $response = $this->actingAs($user)->get('/guardian');
        $response->assertStatus(200);
    }

    public function test_unauthorized_user_gets_403(): void
    {
        Gate::define('viewGuardianDashboard', fn ($user) => false);

        $user = new class extends Authenticatable
        {
            public $id = 1;

            protected $guarded = [];
        };

        $response = $this->actingAs($user)->get('/guardian');
        $response->assertStatus(403);
    }

    public function test_ip_whitelist_blocks_non_listed_ip(): void
    {
        Gate::define('viewGuardianDashboard', fn ($user) => true);
        config(['guardian.dashboard.allowed_ips' => ['10.0.0.1']]);

        $user = new class extends Authenticatable
        {
            public $id = 1;

            protected $guarded = [];
        };

        $response = $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => '192.168.1.1'])
            ->get('/guardian');
        $response->assertStatus(403);
    }

    public function test_empty_ip_whitelist_allows_all(): void
    {
        Gate::define('viewGuardianDashboard', fn ($user) => true);
        config(['guardian.dashboard.allowed_ips' => []]);

        $user = new class extends Authenticatable
        {
            public $id = 1;

            protected $guarded = [];
        };

        $response = $this->actingAs($user)->get('/guardian');
        $response->assertStatus(200);
    }
}
