<?php

namespace Tests\Feature;

use App\Services\MatchingService;
use Tests\ApiTestCase;

/**
 * TC-12 (partie controle d'acces du matching, EF-09 / EF-13).
 * Le service de calcul est simule : on teste ici le controleur.
 */
class MatchingAccessTest extends ApiTestCase
{
    private function simulerService(): void
    {
        $this->mock(MatchingService::class, function ($mock) {
            $mock->shouldReceive('calculateMatchingForOffer')->andReturn([
                ['score' => 82.5],
                ['score' => 40.0],
            ]);
        });
    }

    public function test_tc12_un_etudiant_ne_peut_pas_consulter_le_matching(): void
    {
        $this->simulerService();
        $offre = $this->makeOffer($this->makeUser('entreprise'));

        $this->loginAs($this->makeUser('etudiant'))
            ->getJson("/api/company/offers/{$offre->id}/matching")
            ->assertStatus(403);
    }

    public function test_tc12_matching_de_l_offre_d_une_autre_entreprise_introuvable(): void
    {
        $this->simulerService();
        $offre = $this->makeOffer($this->makeUser('entreprise'));

        $this->loginAs($this->makeUser('entreprise'))
            ->getJson("/api/company/offers/{$offre->id}/matching")
            ->assertStatus(404);
    }

    public function test_tc12_le_proprietaire_obtient_le_classement(): void
    {
        $this->simulerService();
        $ent = $this->makeUser('entreprise');
        $offre = $this->makeOffer($ent);

        $this->loginAs($ent)
            ->getJson("/api/company/offers/{$offre->id}/matching")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('offer_id', $offre->id)
            ->assertJsonCount(2, 'matching_results');
    }

    public function test_tc12_matching_sans_jeton_refuse(): void
    {
        $offre = $this->makeOffer($this->makeUser('entreprise'));

        $this->asGuest()->getJson("/api/company/offers/{$offre->id}/matching")->assertStatus(401);
    }
}
