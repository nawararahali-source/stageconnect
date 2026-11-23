<?php

namespace Tests\Feature;

use Tests\ApiTestCase;

/** TC-06 a TC-09 : candidatures (EF-07, EF-08, EF-06). */
class ApplicationTest extends ApiTestCase
{
    // ---------------- TC-06 : cas nominal ----------------
    public function test_tc06_un_etudiant_postule_et_voit_sa_candidature(): void
    {
        $ent = $this->makeUser('entreprise');
        $etu = $this->makeUser('etudiant');
        $offre = $this->makeOffer($ent);

        $this->loginAs($etu)->postJson("/api/student/offers/{$offre->id}/apply")
            ->assertStatus(201)
            ->assertJsonPath('application.status', 'pending');

        $this->loginAs($etu)->getJson('/api/student/applications')
            ->assertOk()
            ->assertJsonCount(1, 'applications')
            ->assertJsonPath('applications.0.status', 'pending');
    }

    // ---------------- TC-07 : table de decision ----------------
    public function test_tc07_regle1_une_entreprise_ne_peut_pas_postuler(): void
    {
        $ent = $this->makeUser('entreprise');
        $offre = $this->makeOffer($ent);

        $this->loginAs($ent)->postJson("/api/student/offers/{$offre->id}/apply")->assertStatus(403);
    }

    public function test_tc07_regle2_offre_fermee(): void
    {
        $ent = $this->makeUser('entreprise');
        $etu = $this->makeUser('etudiant');
        $offre = $this->makeOffer($ent, ['status' => 'closed']);

        $this->loginAs($etu)->postJson("/api/student/offers/{$offre->id}/apply")->assertStatus(400);
    }

    public function test_tc07_regle3_date_limite_depassee(): void
    {
        $ent = $this->makeUser('entreprise');
        $etu = $this->makeUser('etudiant');
        $offre = $this->makeOffer($ent, ['deadline' => date('Y-m-d', strtotime('-1 day'))]);

        $this->loginAs($etu)->postJson("/api/student/offers/{$offre->id}/apply")->assertStatus(400);
    }

    public function test_tc07_regle4_candidature_en_double(): void
    {
        $ent = $this->makeUser('entreprise');
        $etu = $this->makeUser('etudiant');
        $offre = $this->makeOffer($ent);

        $this->loginAs($etu)->postJson("/api/student/offers/{$offre->id}/apply")->assertStatus(201);
        $this->loginAs($etu)->postJson("/api/student/offers/{$offre->id}/apply")->assertStatus(409);

        $this->assertDatabaseCount('applications', 1);
    }

    public function test_tc07_regle5_aucune_place_disponible(): void
    {
        $ent = $this->makeUser('entreprise');
        $autre = $this->makeUser('etudiant');
        $etu = $this->makeUser('etudiant');
        $offre = $this->makeOffer($ent, ['positions' => 1]);
        $this->makeApplication($autre, $offre, 'accepted');

        $this->loginAs($etu)->postJson("/api/student/offers/{$offre->id}/apply")->assertStatus(409);
    }

    public function test_tc07_regle6_toutes_les_conditions_remplies(): void
    {
        $ent = $this->makeUser('entreprise');
        $etu = $this->makeUser('etudiant');
        $offre = $this->makeOffer($ent);

        $this->loginAs($etu)->postJson("/api/student/offers/{$offre->id}/apply")->assertStatus(201);
        $this->assertDatabaseHas('applications', [
            'student_id' => $etu->id,
            'internship_offer_id' => $offre->id,
            'status' => 'pending',
        ]);
    }

    // ---------------- TC-08 : transitions de statut ----------------
    private function contexteStatut(int $places = 2): array
    {
        $ent1 = $this->makeUser('entreprise');
        $ent2 = $this->makeUser('entreprise');
        $etu = $this->makeUser('etudiant');
        $offre = $this->makeOffer($ent1, ['positions' => $places]);
        $candidature = $this->makeApplication($etu, $offre);

        return [$ent1, $ent2, $etu, $offre, $candidature];
    }

    private function changerStatut($acteur, $candidature, string $statut)
    {
        return $this->loginAs($acteur)
            ->patchJson("/api/company/applications/{$candidature->id}/status", ['status' => $statut]);
    }

    public function test_tc08_accepter_puis_refuser_puis_reaccepter(): void
    {
        [$ent1, , , , $candidature] = $this->contexteStatut();

        $this->changerStatut($ent1, $candidature, 'accepted')->assertOk();
        $this->assertDatabaseHas('applications', ['id' => $candidature->id, 'status' => 'accepted']);

        $this->changerStatut($ent1, $candidature, 'rejected')->assertOk();
        $this->assertDatabaseHas('applications', ['id' => $candidature->id, 'status' => 'rejected']);

        $this->changerStatut($ent1, $candidature, 'accepted')->assertOk();
        $this->assertDatabaseHas('applications', ['id' => $candidature->id, 'status' => 'accepted']);
    }

    public function test_tc08_statut_pending_ou_inconnu_refuse(): void
    {
        [$ent1, , , , $candidature] = $this->contexteStatut();

        $this->changerStatut($ent1, $candidature, 'pending')->assertStatus(422);
        $this->changerStatut($ent1, $candidature, 'xyz')->assertStatus(422);
        $this->assertDatabaseHas('applications', ['id' => $candidature->id, 'status' => 'pending']);
    }

    public function test_tc08_une_autre_entreprise_ne_peut_pas_changer_le_statut(): void
    {
        [, $ent2, , , $candidature] = $this->contexteStatut();

        $this->changerStatut($ent2, $candidature, 'accepted')->assertStatus(404);
        $this->assertDatabaseHas('applications', ['id' => $candidature->id, 'status' => 'pending']);
    }

    public function test_tc08_un_etudiant_ne_peut_pas_changer_le_statut(): void
    {
        [, , $etu, , $candidature] = $this->contexteStatut();

        $this->changerStatut($etu, $candidature, 'accepted')->assertStatus(403);
    }

    public function test_tc08_accepter_une_offre_complete_refuse(): void
    {
        $ent1 = $this->makeUser('entreprise');
        $etuA = $this->makeUser('etudiant');
        $etuB = $this->makeUser('etudiant');
        $offre = $this->makeOffer($ent1, ['positions' => 1]);
        $this->makeApplication($etuA, $offre, 'accepted');
        $candidatureB = $this->makeApplication($etuB, $offre, 'rejected');

        $this->changerStatut($ent1, $candidatureB, 'accepted')->assertStatus(409);
        $this->assertDatabaseHas('applications', ['id' => $candidatureB->id, 'status' => 'rejected']);
    }

    // ---------------- TC-09 : valeurs limites de capacite ----------------
    private function donneesOffre(array $surcharge = []): array
    {
        return array_merge([
            'title' => 'Stage Developpeur Laravel',
            'domain' => 'Informatique',
            'description' => 'Description de test',
            'duration' => '3 mois',
            'location' => 'Mahdia',
            'work_mode' => 'onsite',
            'required_skills' => ['PHP'],
            'positions' => 2,
            'deadline' => date('Y-m-d', strtotime('+1 day')),
            'status' => 'open',
        ], $surcharge);
    }

    public function test_tc09_capacite_de_l_offre(): void
    {
        $ent1 = $this->makeUser('entreprise');
        $etu1 = $this->makeUser('etudiant');
        $etu2 = $this->makeUser('etudiant');
        $etu3 = $this->makeUser('etudiant');
        $offreD = $this->makeOffer($ent1, ['positions' => 2]);
        $this->makeApplication($etu1, $offreD, 'accepted');
        $this->makeApplication($etu2, $offreD, 'accepted');
        $troisieme = $this->makeApplication($etu3, $offreD, 'pending');

        // 1) accepter une 3e candidature : refuse
        $this->changerStatut($ent1, $troisieme, 'accepted')->assertStatus(409);

        // 2) passer les places a 1 alors que 2 sont acceptees : refuse
        $this->loginAs($ent1)->putJson("/api/company/offers/{$offreD->id}", $this->donneesOffre(['positions' => 1]))
            ->assertStatus(422);

        // 3) laisser les places a 2 : accepte
        $this->loginAs($ent1)->putJson("/api/company/offers/{$offreD->id}", $this->donneesOffre(['positions' => 2]))
            ->assertOk();
    }

    public function test_tc09_une_offre_complete_disparait_de_la_liste_des_etudiants(): void
    {
        $ent1 = $this->makeUser('entreprise');
        $etu1 = $this->makeUser('etudiant');
        $etu2 = $this->makeUser('etudiant');
        $voyeur = $this->makeUser('etudiant');
        $offreD = $this->makeOffer($ent1, ['positions' => 2]);
        $this->makeApplication($etu1, $offreD, 'accepted');
        $this->makeApplication($etu2, $offreD, 'accepted');

        $reponse = $this->loginAs($voyeur)->getJson('/api/student/offers')->assertOk();

        $this->assertNotContains($offreD->id, collect($reponse->json('offers'))->pluck('id')->all());
    }
}
