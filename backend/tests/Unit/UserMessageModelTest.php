<?php

namespace Tests\Unit;

use App\Models\Application;
use App\Models\Message;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class UserMessageModelTest extends TestCase
{
    public function test_mot_de_passe_masque_dans_la_serialisation(): void
    {
        $user = new User([
            'first_name' => 'Nawara',
            'last_name' => 'Rahali',
            'email' => 'nawara@test.local',
            'password' => 'secret',
            'role' => 'etudiant',
        ]);

        $this->assertArrayNotHasKey('password', $user->toArray());
    }

    public function test_date_de_lecture_du_message_est_une_date(): void
    {
        $message = new Message();
        $message->setRawAttributes(['read_at' => '2026-01-01 10:00:00']);

        $this->assertInstanceOf(Carbon::class, $message->read_at);
    }

    public function test_date_de_candidature_est_une_date(): void
    {
        $candidature = new Application();
        $candidature->setRawAttributes(['applied_at' => '2026-01-01 10:00:00']);

        $this->assertInstanceOf(Carbon::class, $candidature->applied_at);
    }

    /**
     * Anomalie attendue : UserFactory utilise encore 'name' alors que la table
     * users contient first_name et last_name.
     */
    public function test_factory_utilisateur_coherente_avec_le_schema(): void
    {
        $attributs = User::factory()->make()->getAttributes();

        $this->assertArrayHasKey('first_name', $attributs);
        $this->assertArrayHasKey('last_name', $attributs);
        $this->assertArrayNotHasKey('name', $attributs);
    }
}
