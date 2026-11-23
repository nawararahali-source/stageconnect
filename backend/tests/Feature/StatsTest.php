<?php

namespace Tests\Feature;

use App\Services\MatchingService;
use Tests\ApiTestCase;

/** TC-15 : statistiques et tranches de score (EF-12, EF-13). */
class StatsTest extends ApiTestCase
{
    public function test_tc15_les_statistiques_etudiant_ne_comptent_que_ses_candidatures(): void
    {
        $ent = $this->makeUser('entreprise');
        $etu1 = $this->makeUser('etudiant');
        $etu2 = $this->makeUser('etudiant');
        $offreA = $this->makeOffer($ent);
        $offreB = $this->makeOffer($ent);
        $this->makeApplication($etu1, $offreA, 'pending');
        $this->makeApplication($etu1, $offreB, 'accepted');
        $this->makeApplication($etu2, $offreA, 'rejected');

        $this->loginAs($etu1)->getJson('/api/student/stats')
            ->assertOk()
            ->assertJsonPath('applications_by_status.pending', 1)
            ->assertJsonPath('applications_by_status.accepted', 1)
            ->assertJsonPath('applications_by_status.rejected', 0);
    }

    public function test_tc15_une_entreprise_ne_peut_pas_lire_les_stats_etudiant(): void
    {
        $this->loginAs($this->makeUser('entreprise'))->getJson('/api/student/stats')->assertStatus(403);
    }

    public function test_tc15_un_etudiant_ne_peut_pas_lire_les_stats_entreprise(): void
    {
        $this->loginAs($this->makeUser('etudiant'))->getJson('/api/company/stats')->assertStatus(403);
    }

    public function test_tc15_tranches_de_score_aux_bornes_25_et_100(): void
    {
        $this->mock(MatchingService::class, function ($mock) {
            $mock->shouldReceive('calculateMatchingForOffer')->andReturn([
                ['score' => 25],
                ['score' => 100],
            ]);
        });
        $ent = $this->makeUser('entreprise');
        $this->makeOffer($ent);

        $reponse = $this->loginAs($ent)->getJson('/api/company/stats')->assertOk();

        $reponse->assertJsonPath('score_distribution.0.count', 0)   // 0-25
            ->assertJsonPath('score_distribution.1.label', '25-50')
            ->assertJsonPath('score_distribution.1.count', 1)       // 25 -> tranche 25-50
            ->assertJsonPath('score_distribution.2.count', 0)       // 50-75
            ->assertJsonPath('score_distribution.3.label', '75-100')
            ->assertJsonPath('score_distribution.3.count', 1);      // 100 -> derniere tranche
    }

    public function test_tc15_statistiques_entreprise_par_statut(): void
    {
        $this->mock(MatchingService::class, function ($mock) {
            $mock->shouldReceive('calculateMatchingForOffer')->andReturn([]);
        });
        $ent = $this->makeUser('entreprise');
        $offre = $this->makeOffer($ent, ['positions' => 3]);
        $this->makeApplication($this->makeUser('etudiant'), $offre, 'pending');
        $this->makeApplication($this->makeUser('etudiant'), $offre, 'accepted');

        $this->loginAs($ent)->getJson('/api/company/stats')
            ->assertOk()
            ->assertJsonPath('applications_by_status.pending', 1)
            ->assertJsonPath('applications_by_status.accepted', 1)
            ->assertJsonPath('applications_by_offer.0.count', 2);
    }
}
