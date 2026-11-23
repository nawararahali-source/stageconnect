<?php

namespace Tests\Feature;

use App\Models\InternshipOffer;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CoverLetterGenerationTest extends TestCase
{
    use DatabaseTransactions;

    private array $previousGeminiEnvironment = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['GEMINI_API_KEY'] as $name) {
            $this->previousGeminiEnvironment[$name] = [
                'env' => $_ENV[$name] ?? null,
                'server' => $_SERVER[$name] ?? null,
                'process' => getenv($name),
            ];
        }
        $this->setGeminiApiKey('test-gemini-api-key');
    }

    protected function tearDown(): void
    {
        foreach ($this->previousGeminiEnvironment as $name => $values) {
            foreach (['env' => '_ENV', 'server' => '_SERVER'] as $source => $superglobal) {
                if ($values[$source] === null) {
                    unset($GLOBALS[$superglobal][$name]);
                } else {
                    $GLOBALS[$superglobal][$name] = $values[$source];
                }
            }

            if ($values['process'] === false) {
                putenv($name);
            } else {
                putenv($name . '=' . $values['process']);
            }
        }

        parent::tearDown();
    }

    public function test_student_can_generate_a_fact_based_cover_letter_through_gemini(): void
    {
        $letter = implode(' ', array_fill(0, 220, 'motivation'));
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/interactions' => Http::response([
                'steps' => [[
                    'type' => 'model_output',
                    'content' => [['text' => $letter]],
                ]],
            ], 200),
        ]);

        $student = $this->createUser('etudiant', 'Amira', 'Ben Ali');
        Profile::create([
            'user_id' => $student->id,
            'formation' => 'Licence en informatique',
            'specialite' => 'Développement web',
            'niveau' => 'L3',
            'experiences' => [['poste' => 'Stagiaire développeuse', 'entreprise' => 'Tech TN']],
            'projects' => [['nom' => 'Application de gestion', 'technologies' => 'Laravel']],
        ]);
        $student->skills()->create(['name' => 'PHP', 'level' => 'avancé']);

        $company = $this->createUser('entreprise', 'Société', 'Exemple');
        $offer = $company->internshipOffers()->create([
            'title' => 'Stage développeur backend',
            'domain' => 'Informatique',
            'description' => 'Participation au développement d’API.',
            'duration' => '3 mois',
            'location' => 'Tunis',
            'work_mode' => 'hybrid',
            'required_skills' => ['PHP', 'Laravel'],
            'positions' => 1,
            'deadline' => null,
            'status' => 'open',
        ]);

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/offers/{$offer->id}/cover-letter")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('cover_letter', $letter);

        Http::assertSent(function ($request) {
            $prompt = $request['input'];

            return $request->url() === 'https://generativelanguage.googleapis.com/v1beta/interactions'
                && $request->hasHeader('x-goog-api-key', 'test-gemini-api-key')
                && strpos($prompt, '200 à 250 mots') !== false
                && strpos($prompt, 'Licence en informatique') !== false
                && strpos($prompt, 'Application de gestion') !== false
                && strpos($prompt, 'Stage développeur backend') !== false
                && strpos($prompt, 'Laravel') !== false;
        });
    }

    public function test_gemini_errors_are_returned_as_a_clear_message(): void
    {
        Http::fake([
            '*' => Http::response(['error' => ['message' => 'Unavailable']], 503),
        ]);

        $student = $this->createUser('etudiant', 'Amira', 'Ben Ali');
        Profile::create([
            'user_id' => $student->id,
            'formation' => 'Licence',
        ]);
        $company = $this->createUser('entreprise', 'Société', 'Exemple');
        $offer = $company->internshipOffers()->create([
            'title' => 'Stage',
            'domain' => 'Informatique',
            'description' => 'Description du stage',
            'duration' => '3 mois',
            'location' => 'Tunis',
            'work_mode' => 'onsite',
            'required_skills' => [],
            'positions' => 1,
            'deadline' => null,
            'status' => 'open',
        ]);

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/offers/{$offer->id}/cover-letter")
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Le service Gemini est temporairement indisponible. Veuillez réessayer dans quelques minutes.');
    }

    private function setGeminiApiKey(string $apiKey): void
    {
        putenv('GEMINI_API_KEY=' . $apiKey);
        $_ENV['GEMINI_API_KEY'] = $apiKey;
        $_SERVER['GEMINI_API_KEY'] = $apiKey;
    }

    private function createUser(string $role, string $firstName, string $lastName): User
    {
        return User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => uniqid('cover-letter-', true) . '@example.test',
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }
}
