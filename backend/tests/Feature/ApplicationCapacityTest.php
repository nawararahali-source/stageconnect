<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\InternshipOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApplicationCapacityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_only_accepted_applications_reduce_available_positions(): void
    {
        $company = $this->createUser('entreprise');
        $pendingStudent = $this->createUser('etudiant');
        $rejectedStudent = $this->createUser('etudiant');
        $offer = $company->internshipOffers()->create($this->offerData(3));
        $pending = $this->makeApplication($offer, $pendingStudent, 'pending');
        $this->makeApplication($offer, $rejectedStudent, 'rejected');

        $this->actingAs($pendingStudent, 'sanctum')
            ->getJson('/api/student/offers')
            ->assertOk()
            ->assertJsonPath('offers.0.positions', 3)
            ->assertJsonPath('offers.0.accepted_applications_count', 0)
            ->assertJsonPath('offers.0.available_positions', 3);

        $this->actingAs($company, 'sanctum')
            ->patchJson("/api/company/applications/{$pending->id}/status", ['status' => 'accepted'])
            ->assertOk();

        $this->actingAs($pendingStudent, 'sanctum')
            ->getJson('/api/student/offers')
            ->assertOk()
            ->assertJsonPath('offers.0.positions', 3)
            ->assertJsonPath('offers.0.accepted_applications_count', 1)
            ->assertJsonPath('offers.0.available_positions', 2);

        $this->assertDatabaseHas('internship_offers', [
            'id' => $offer->id,
            'positions' => 3,
        ]);
    }

    public function test_student_offer_disappears_when_all_positions_are_accepted(): void
    {
        $company = $this->createUser('entreprise');
        $firstStudent = $this->createUser('etudiant');
        $secondStudent = $this->createUser('etudiant');
        $offer = $company->internshipOffers()->create($this->offerData(2));
        $firstApplication = $this->makeApplication($offer, $firstStudent, 'pending');
        $secondApplication = $this->makeApplication($offer, $secondStudent, 'pending');

        $this->actingAs($company, 'sanctum')
            ->patchJson("/api/company/applications/{$firstApplication->id}/status", ['status' => 'accepted'])
            ->assertOk();

        $this->actingAs($firstStudent, 'sanctum')
            ->getJson('/api/student/offers')
            ->assertOk()
            ->assertJsonPath('offers.0.available_positions', 1);

        $this->actingAs($company, 'sanctum')
            ->patchJson("/api/company/applications/{$secondApplication->id}/status", ['status' => 'accepted'])
            ->assertOk();

        $this->actingAs($secondStudent, 'sanctum')
            ->getJson('/api/student/offers')
            ->assertOk()
            ->assertJsonMissing(['id' => $offer->id]);

        $this->actingAs($company, 'sanctum')
            ->getJson('/api/company/offers')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $offer->id,
                'positions' => 2,
                'available_positions' => 0,
            ]);
    }

    public function test_student_cannot_apply_when_no_positions_are_available(): void
    {
        $company = $this->createUser('entreprise');
        $acceptedStudent = $this->createUser('etudiant');
        $newStudent = $this->createUser('etudiant');
        $offer = $company->internshipOffers()->create($this->offerData(1));
        $this->makeApplication($offer, $acceptedStudent, 'accepted');

        $this->actingAs($newStudent, 'sanctum')
            ->postJson("/api/student/offers/{$offer->id}/apply")
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_acceptance_respects_capacity_and_rejection_releases_a_position(): void
    {
        $company = $this->createUser('entreprise');
        $firstStudent = $this->createUser('etudiant');
        $secondStudent = $this->createUser('etudiant');
        $offer = $company->internshipOffers()->create($this->offerData(1));
        $accepted = $this->makeApplication($offer, $firstStudent, 'accepted');
        $pending = $this->makeApplication($offer, $secondStudent, 'pending');
        $this->actingAs($company, 'sanctum');

        $this->patchJson("/api/company/applications/{$pending->id}/status", ['status' => 'accepted'])
            ->assertStatus(409);

        $this->patchJson("/api/company/applications/{$accepted->id}/status", ['status' => 'accepted'])
            ->assertOk();

        $this->patchJson("/api/company/applications/{$accepted->id}/status", ['status' => 'rejected'])
            ->assertOk();

        $this->patchJson("/api/company/applications/{$pending->id}/status", ['status' => 'accepted'])
            ->assertOk();

        $this->assertSame(1, Application::where('internship_offer_id', $offer->id)
            ->where('status', 'accepted')
            ->count());
        $this->assertDatabaseHas('internship_offers', [
            'id' => $offer->id,
            'positions' => 1,
        ]);
    }

    public function test_company_cannot_reduce_total_positions_below_accepted_applications(): void
    {
        $company = $this->createUser('entreprise');
        $student = $this->createUser('etudiant');
        $secondStudent = $this->createUser('etudiant');
        $offer = $company->internshipOffers()->create($this->offerData(2));
        $this->makeApplication($offer, $student, 'accepted');
        $this->makeApplication($offer, $secondStudent, 'accepted');

        $this->actingAs($company, 'sanctum')
            ->putJson("/api/company/offers/{$offer->id}", $this->offerData(1))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('positions');

        $this->assertDatabaseHas('internship_offers', [
            'id' => $offer->id,
            'positions' => 2,
        ]);
    }

    private function createUser(string $role): User
    {
        return User::create([
            'first_name' => 'Capacity',
            'last_name' => 'Test',
            'email' => uniqid('capacity-test-', true) . '@example.test',
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }

    private function makeApplication(InternshipOffer $offer, User $student, string $status): Application
    {
        return $offer->applications()->create([
            'student_id' => $student->id,
            'status' => $status,
        ]);
    }

    private function offerData(int $positions): array
    {
        return [
            'title' => 'Stage de test',
            'domain' => 'Informatique',
            'description' => 'Description du stage.',
            'duration' => '3 mois',
            'location' => 'Tunis',
            'work_mode' => 'hybrid',
            'required_skills' => ['Laravel'],
            'positions' => $positions,
            'deadline' => null,
            'status' => 'open',
        ];
    }
}
