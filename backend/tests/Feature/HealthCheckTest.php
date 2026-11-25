<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_sonde_de_sante_repond_200_avec_la_base_active(): void
    {
        $this->getJson('/api/health')
            ->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('database', 'up')
            ->assertJsonStructure(['status', 'database', 'version', 'time']);
    }
}