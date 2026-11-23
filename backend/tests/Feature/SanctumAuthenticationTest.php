<?php

namespace Tests\Feature;

use Tests\TestCase;

class SanctumAuthenticationTest extends TestCase
{
    public function test_protected_profile_route_rejects_unauthenticated_requests(): void
    {
        $this->postJson('/api/profile', [
            'formation' => 'Licence informatique',
            'specialite' => 'Développement',
            'niveau' => 'Licence 3',
        ])
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Non authentifié',
            ]);
    }
}
