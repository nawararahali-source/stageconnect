<?php

namespace Tests\Feature;

use App\Models\InternshipOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_company_stats_are_scoped_and_include_status_offers_and_score_bands(): void
    {
        Http::fake([
            '*' => Http::response($this->prediction(), 200),
        ]);

        $company = $this->createUser('entreprise');
        $otherCompany = $this->createUser('entreprise');
        $students = [
            $this->createUser('etudiant'),
            $this->createUser('etudiant'),
            $this->createUser('etudiant'),
            $this->createUser('etudiant'),
        ];
        $offer = $this->createOffer($company, 'Stage A');
        $this->createTestApplication($offer, $students[0], 'pending');
        $this->createTestApplication($offer, $students[1], 'accepted');
        $this->createTestApplication($offer, $students[2], 'rejected');
        $this->createTestApplication($offer, $students[3], 'pending');
        $otherOffer = $this->createOffer($otherCompany, 'Offre privée');
        $this->createTestApplication($otherOffer, $this->createUser('etudiant'), 'accepted');

        $this->actingAs($company, 'sanctum')
            ->getJson('/api/company/stats')
            ->assertOk()
            ->assertJsonPath('applications_by_status.pending', 2)
            ->assertJsonPath('applications_by_status.accepted', 1)
            ->assertJsonPath('applications_by_status.rejected', 1)
            ->assertJsonCount(1, 'applications_by_offer')
            ->assertJsonPath('applications_by_offer.0.offer_title', 'Stage A')
            ->assertJsonPath('applications_by_offer.0.count', 4)
            ->assertJsonPath('score_distribution.0.label', '0-25')
            ->assertJsonPath('score_distribution.0.count', 4)
            ->assertJsonPath('score_distribution.1.count', 0)
            ->assertJsonPath('score_distribution.2.count', 0)
            ->assertJsonPath('score_distribution.3.count', 0);
    }

    public function test_student_stats_only_include_the_authenticated_students_applications(): void
    {
        $student = $this->createUser('etudiant');
        $otherStudent = $this->createUser('etudiant');
        $company = $this->createUser('entreprise');
        $this->createTestApplication($this->createOffer($company, 'Stage en attente'), $student, 'pending');
        $this->createTestApplication($this->createOffer($company, 'Stage accepté'), $student, 'accepted');
        $this->createTestApplication($this->createOffer($company, 'Stage refusé'), $student, 'rejected');
        $offer = $this->createOffer($company, 'Stage autre étudiant');
        $this->createTestApplication($offer, $otherStudent, 'accepted');

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/student/stats')
            ->assertOk()
            ->assertJsonPath('applications_by_status.pending', 1)
            ->assertJsonPath('applications_by_status.accepted', 1)
            ->assertJsonPath('applications_by_status.rejected', 1);
    }

    private function prediction(): array
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
                'poids_pourcent' => 0,
                'contribution' => 0,
            ];
        }

        return [
            'score' => 12,
            'niveau' => 'Faible',
            'contributions' => $contributions,
            'source' => 'ml',
        ];
    }

    private function createOffer(User $company, string $title): InternshipOffer
    {
        return $company->internshipOffers()->create([
            'title' => $title,
            'domain' => 'Informatique',
            'description' => 'Description du stage.',
            'duration' => '3 mois',
            'location' => 'Tunis',
            'work_mode' => 'hybrid',
            'required_skills' => ['Laravel'],
            'positions' => 2,
            'deadline' => null,
            'status' => 'open',
        ]);
    }

    private function createTestApplication(InternshipOffer $offer, User $student, string $status): void
    {
        $offer->applications()->create([
            'student_id' => $student->id,
            'status' => $status,
        ]);
    }

    private function createUser(string $role): User
    {
        return User::create([
            'first_name' => 'Dashboard',
            'last_name' => 'Stats',
            'email' => uniqid('dashboard-stats-', true) . '@example.test',
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }
}
