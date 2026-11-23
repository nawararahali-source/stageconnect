<?php

namespace Tests;

use App\Models\Application;
use App\Models\InternshipOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

/**
 * Base des tests d'intégration StageConnect (API Laravel + MySQL de test).
 */
abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    public const PASSWORD = 'Passw0rd!';

    protected function setUp(): void
    {
        // Garde-fou : ne jamais réinitialiser une autre base que stageconnect_test.
        $this->refreshApplication();
        $connexion = config('database.default');
        $base = config("database.connections.$connexion.database");
        if ($base !== 'stageconnect_test') {
            throw new \RuntimeException(
                "Base de test inattendue : '$base' (attendue : stageconnect_test). Verifiez phpunit.xml."
            );
        }

        parent::setUp();
    }

    // ------------------------------------------------------------------
    // Jeux de données
    // ------------------------------------------------------------------
    protected function makeUser(string $role = 'etudiant', ?string $email = null, string $prenom = 'Test'): User
    {
        return User::create([
            'first_name' => $prenom,
            'last_name' => ucfirst($role),
            'email' => $email ?? str_replace('.', '', uniqid($role . '_', true)) . '@test.local',
            'password' => Hash::make(self::PASSWORD),
            'role' => $role,
        ]);
    }

    protected function makeOffer(User $entreprise, array $surcharge = []): InternshipOffer
    {
        return InternshipOffer::create(array_merge([
            'company_id' => $entreprise->id,
            'title' => 'Stage Developpeur Laravel',
            'domain' => 'Informatique',
            'description' => 'Description de test',
            'duration' => '3 mois',
            'location' => 'Mahdia',
            'work_mode' => 'onsite',
            'required_skills' => ['PHP', 'Laravel'],
            'positions' => 2,
            'deadline' => date('Y-m-d', strtotime('+1 day')),
            'status' => 'open',
        ], $surcharge));
    }

    protected function makeApplication(User $etudiant, InternshipOffer $offre, string $statut = 'pending'): Application
    {
        return Application::create([
            'student_id' => $etudiant->id,
            'internship_offer_id' => $offre->id,
            'status' => $statut,
        ]);
    }

    // ------------------------------------------------------------------
    // Authentification dans les tests
    // ------------------------------------------------------------------
    /** Reinitialise l'authentification (les requetes d'un meme test partagent l'application). */
    protected function resetAuth(): void
    {
        $this->flushHeaders();
        $this->flushSession();
        $this->app['auth']->forgetGuards();
    }

    /** Les requetes suivantes sont faites par cet utilisateur. */
    protected function loginAs(User $user): static
    {
        $this->resetAuth();
        Sanctum::actingAs($user);

        return $this;
    }

    /** Les requetes suivantes utilisent un vrai jeton Sanctum. */
    protected function useToken(string $token): static
    {
        $this->resetAuth();

        return $this->withToken($token);
    }

    /** Les requetes suivantes n'ont aucun jeton. */
    protected function asGuest(): static
    {
        $this->resetAuth();

        return $this;
    }
}
