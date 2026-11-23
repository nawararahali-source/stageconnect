<?php

namespace Tests\Feature;

use App\Models\InternshipOffer;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MatchingAnalysisTest extends TestCase
{
    use DatabaseTransactions;

    public function test_matching_recognizes_accounting_domains_with_accents_and_related_studies(): void
    {
        Http::fake([
            '*' => Http::response($this->mlPrediction(71.5), 200),
        ]);

        $company = $this->createUser('entreprise');
        $accountingStudent = $this->createStudent('Gestion', 'Comptabilité');
        $financeStudent = $this->createStudent('Finance', 'Gestion financière');
        $offer = $company->internshipOffers()->create([
            'title' => 'Stage comptable',
            'domain' => 'Comptabilite',
            'description' => 'Participation aux opérations comptables.',
            'duration' => '3 mois',
            'location' => 'Tunis',
            'work_mode' => 'onsite',
            'required_skills' => [],
            'positions' => 2,
            'deadline' => null,
            'status' => 'open',
        ]);
        $accountingApplication = $offer->applications()->create([
            'student_id' => $accountingStudent->id,
            'status' => 'pending',
        ]);
        $financeApplication = $offer->applications()->create([
            'student_id' => $financeStudent->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($company, 'sanctum')
            ->getJson("/api/company/offers/{$offer->id}/matching")
            ->assertOk()
            ->assertJsonPath('success', true);

        $results = collect($response->json('matching_results'))->keyBy('application_id');

        $this->assertEquals(
            20,
            $results->get($accountingApplication->id)['criteria']['domain']['score']
        );
        $this->assertEquals(
            15,
            $results->get($financeApplication->id)['criteria']['domain']['score']
        );
    }

    public function test_matching_recognizes_accounting_skills_when_study_domain_is_general(): void
    {
        Http::fake([
            '*' => Http::response($this->mlPrediction(71.5), 200),
        ]);

        $company = $this->createUser('entreprise');
        $student = $this->createStudent('Informatique', 'DSI');
        $student->skills()->create(['name' => 'Comptabilité', 'level' => 'avancé']);
        $offer = $company->internshipOffers()->create([
            'title' => 'Stage comptable',
            'domain' => 'Comptabilité',
            'description' => 'Participation aux opérations comptables.',
            'duration' => '3 mois',
            'location' => 'Tunis',
            'work_mode' => 'onsite',
            'required_skills' => [],
            'positions' => 1,
            'deadline' => null,
            'status' => 'open',
        ]);
        $application = $offer->applications()->create([
            'student_id' => $student->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($company, 'sanctum')
            ->getJson("/api/company/offers/{$offer->id}/matching")
            ->assertOk();

        $result = collect($response->json('matching_results'))
            ->firstWhere('application_id', $application->id);

        $this->assertSame(20, $result['criteria']['domain']['score']);
        $this->assertTrue($result['criteria']['domain']['applicable']);
    }

    public function test_matching_uses_ml_score_and_sends_expected_features(): void
    {
        Config::set('services.ml_service.url', 'http://127.0.0.1:8001');
        Http::fake([
            '*' => Http::response($this->mlPrediction(88.25), 200),
        ]);

        [$company, $student, $offer, $application] = $this->createMatchingScenario();
        $student->skills()->create(['name' => 'PHP', 'level' => 'avancé']);

        $response = $this->actingAs($company, 'sanctum')
            ->getJson("/api/company/offers/{$offer->id}/matching")
            ->assertOk();

        $result = collect($response->json('matching_results'))
            ->firstWhere('application_id', $application->id);

        $this->assertEquals(88.3, $result['score']);
        $this->assertSame('ml', $result['source']);
        $this->assertSame('PHP', $result['matching_skills'][0]['name']);
        $this->assertSame(['SQL'], $result['missing_skills']);
        $this->assertArrayHasKey('skills_match_ratio', $result['contributions']);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://127.0.0.1:8001/predict'
                && $request['skills_match_ratio'] === 0.5
                && $request['nb_skills_matched'] === 1
                && $request['avg_skill_level'] === 3.0
                && $request['domain_match'] === 1
                && $request['study_level'] === 3
                && $request['location_match'] === 1;
        });
    }

    public function test_matching_falls_back_to_weighted_rules_when_ml_service_fails(): void
    {
        Http::fake([
            '*' => Http::response(['detail' => 'unavailable'], 503),
        ]);

        [$company, , $offer, $application] = $this->createMatchingScenario();

        $response = $this->actingAs($company, 'sanctum')
            ->getJson("/api/company/offers/{$offer->id}/matching")
            ->assertOk();

        $result = collect($response->json('matching_results'))
            ->firstWhere('application_id', $application->id);

        $this->assertSame('rules', $result['source']);
        $this->assertEquals(36.5, $result['score']);
        $this->assertArrayHasKey('skills_match_ratio', $result['contributions']);
        $this->assertEquals(
            $result['score'],
            array_sum(array_column($result['contributions'], 'contribution'))
        );
    }

    public function test_matching_falls_back_when_ml_response_is_invalid(): void
    {
        Http::fake([
            '*' => Http::response(['score' => 'not-a-score', 'contributions' => []], 200),
        ]);

        [$company, , $offer, $application] = $this->createMatchingScenario();

        $response = $this->actingAs($company, 'sanctum')
            ->getJson("/api/company/offers/{$offer->id}/matching")
            ->assertOk();

        $result = collect($response->json('matching_results'))
            ->firstWhere('application_id', $application->id);

        $this->assertSame('rules', $result['source']);
        $this->assertEquals(36.5, $result['score']);
        $this->assertNotEmpty($result['contributions']);
    }

    private function createMatchingScenario(): array
    {
        $company = $this->createUser('entreprise');
        $student = $this->createStudent('Comptabilité', 'Licence L3');
        $offer = $company->internshipOffers()->create([
            'title' => 'Stage comptable',
            'domain' => 'Comptabilité',
            'description' => 'Stage de comptabilité et gestion.',
            'duration' => '3 mois',
            'location' => 'Tunis',
            'work_mode' => 'onsite',
            'required_skills' => ['PHP', 'SQL'],
            'positions' => 1,
            'deadline' => null,
            'status' => 'open',
        ]);
        $application = $offer->applications()->create([
            'student_id' => $student->id,
            'status' => 'pending',
        ]);

        return [$company, $student, $offer, $application];
    }

    private function mlPrediction(float $score): array
    {
        $contributions = [];
        foreach ([
            'skills_match_ratio',
            'nb_skills_matched',
            'avg_skill_level',
            'domain_match',
            'nb_projects',
            'nb_experiences',
            'study_level',
            'location_match',
            'tfidf_similarity',
        ] as $feature) {
            $contributions[$feature] = [
                'valeur' => 0,
                'poids_pourcent' => 10,
                'contribution' => 1,
            ];
        }

        return [
            'score' => $score,
            'niveau' => 'Élevé',
            'contributions' => $contributions,
            'source' => 'ml',
        ];
    }

    private function createStudent(string $specialite, string $formation): User
    {
        $student = $this->createUser('etudiant');
        Profile::create([
            'user_id' => $student->id,
            'specialite' => $specialite,
            'formation' => $formation,
            'niveau' => 'Licence',
            'ville' => 'Tunis',
        ]);

        return $student;
    }

    private function createUser(string $role): User
    {
        return User::create([
            'first_name' => 'Matching',
            'last_name' => 'Test',
            'email' => uniqid('matching-test-', true) . '@example.test',
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }
}
