<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_101st_request_within_a_minute_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        for ($i = 0; $i < 100; $i++) {
            $this->getJson('/api/auth/me')->assertOk();
        }

        $response = $this->getJson('/api/auth/me');
        $response->assertStatus(429);
        $this->assertNotNull($response->headers->get('retry-after'));
    }

    public function test_limit_is_per_user_not_shared(): void
    {
        Sanctum::actingAs(User::factory()->create());
        for ($i = 0; $i < 100; $i++) {
            $this->getJson('/api/auth/me')->assertOk();
        }
        $this->getJson('/api/auth/me')->assertStatus(429);

        // A different user is not affected by the first user's consumption
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/auth/me')->assertOk();
    }
}
