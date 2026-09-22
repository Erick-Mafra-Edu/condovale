<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeployRouteTest extends TestCase
{
    private const VALID_TOKEN = 'a0b1c2d3e4f50a1b2c3d4e5f60718293';

    public function test_it_answers_404_while_the_deployment_is_disabled(): void
    {
        config(['deploy.enabled' => false, 'deploy.token' => self::VALID_TOKEN]);

        $this->postJson('/api/deploy/migrate', ['token' => self::VALID_TOKEN])
            ->assertStatus(404)
            ->assertJson(['status' => false]);
    }

    public function test_it_refuses_to_run_with_a_token_short_enough_to_guess(): void
    {
        config(['deploy.enabled' => true, 'deploy.token' => 'curto']);

        $this->postJson('/api/deploy/migrate', ['token' => 'curto'])
            ->assertStatus(404);
    }

    public function test_it_rejects_a_wrong_token(): void
    {
        config(['deploy.enabled' => true, 'deploy.token' => self::VALID_TOKEN]);

        $this->postJson('/api/deploy/migrate', ['token' => str_repeat('b', 32)])
            ->assertStatus(403)
            ->assertJson(['status' => false]);
    }

    public function test_it_recreates_the_schema_with_the_right_token(): void
    {
        config(['deploy.enabled' => true, 'deploy.token' => self::VALID_TOKEN]);

        $this->withHeader('X-Deploy-Token', self::VALID_TOKEN)
            ->postJson('/api/deploy/migrate')
            ->assertStatus(201)
            ->assertJson(['status' => true]);

        $this->assertTrue(Schema::hasTable('users'));
    }
}
