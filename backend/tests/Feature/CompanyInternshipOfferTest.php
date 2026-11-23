<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CompanyInternshipOfferTest extends TestCase
{
    use DatabaseTransactions;

    public function test_company_can_create_update_list_and_delete_its_offers(): void
    {
        $company = $this->createUser('entreprise');
        $this->actingAs($company, 'sanctum');

        $this->postJson('/api/company/offers', $this->offerData())
            ->assertCreated()
            ->assertJsonPath('offer.title', 'Développeur web')
            ->assertJsonPath('offer.required_skills.0', 'Laravel');

        $offerId = $company->internshipOffers()->firstOrFail()->id;

        $this->getJson('/api/company/offers')
            ->assertOk()
            ->assertJsonCount(1, 'offers');

        $this->putJson("/api/company/offers/{$offerId}", array_merge($this->offerData(), [
            'title' => 'Développeur Laravel',
            'positions' => 2,
        ]))
            ->assertOk()
            ->assertJsonPath('offer.title', 'Développeur Laravel')
            ->assertJsonPath('offer.positions', 2);

        $this->deleteJson("/api/company/offers/{$offerId}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('internship_offers', ['id' => $offerId]);
    }

    public function test_students_cannot_manage_company_offers(): void
    {
        $student = $this->createUser('etudiant');
        $this->actingAs($student, 'sanctum');

        $this->getJson('/api/company/offers')
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->postJson('/api/company/offers', $this->offerData())
            ->assertForbidden();
    }

    public function test_a_company_cannot_modify_another_company_offer(): void
    {
        $owner = $this->createUser('entreprise');
        $otherCompany = $this->createUser('entreprise');
        $offer = $owner->internshipOffers()->create($this->offerData());

        $this->actingAs($otherCompany, 'sanctum');

        $this->putJson("/api/company/offers/{$offer->id}", $this->offerData())
            ->assertNotFound();

        $this->deleteJson("/api/company/offers/{$offer->id}")
            ->assertNotFound();
    }

    private function createUser(string $role): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name' => 'Company',
            'email' => uniqid('offer-test-', true) . '@example.test',
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }

    private function offerData(): array
    {
        return [
            'title' => 'Développeur web',
            'domain' => 'Informatique',
            'description' => 'Développer une application web avec l’équipe.',
            'duration' => '3 mois',
            'location' => 'Tunis',
            'work_mode' => 'hybrid',
            'required_skills' => ['Laravel', 'SQL'],
            'positions' => 1,
            'deadline' => null,
            'status' => 'open',
        ];
    }
}
