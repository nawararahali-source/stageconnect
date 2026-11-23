<?php

namespace Tests\Feature;

use Tests\ApiTestCase;

/** TC-04, TC-05 : gestion des offres et controle d'acces (EF-06, EF-13). */
class OfferTest extends ApiTestCase
{
    private function donnees(array $surcharge = []): array
    {
        return array_merge([
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
        ], $surcharge);
    }

    /** @dataProvider casLimites */
    public function test_tc04_valeurs_limites_de_creation(array $surcharge, int $statutAttendu): void
    {
        $entreprise = $this->makeUser('entreprise');

        $this->loginAs($entreprise)
            ->postJson('/api/company/offers', $this->donnees($surcharge))
            ->assertStatus($statutAttendu);
    }

    public static function casLimites(): array
    {
        return [
            'places 0' => [['positions' => 0], 422],
            'places 1' => [['positions' => 1], 201],
            'places 500' => [['positions' => 500], 201],
            'places 501' => [['positions' => 501], 422],
            'titre 255 caracteres' => [['title' => str_repeat('a', 255)], 201],
            'titre 256 caracteres' => [['title' => str_repeat('a', 256)], 422],
            'mode presentiel inconnu' => [['work_mode' => 'presentiel'], 422],
            'date limite hier' => [['deadline' => date('Y-m-d', strtotime('-1 day'))], 422],
            'date limite aujourd hui' => [['deadline' => date('Y-m-d')], 201],
            '21 competences' => [['required_skills' => array_map(fn ($i) => "Skill $i", range(1, 21))], 422],
        ];
    }

    public function test_tc05_un_etudiant_ne_peut_pas_creer_une_offre(): void
    {
        $etudiant = $this->makeUser('etudiant');

        $this->loginAs($etudiant)
            ->postJson('/api/company/offers', $this->donnees())
            ->assertStatus(403);
    }

    public function test_tc05_une_autre_entreprise_ne_peut_ni_modifier_ni_supprimer(): void
    {
        $ent1 = $this->makeUser('entreprise');
        $ent2 = $this->makeUser('entreprise');
        $offre = $this->makeOffer($ent1);

        $this->loginAs($ent2)
            ->putJson("/api/company/offers/{$offre->id}", $this->donnees())
            ->assertStatus(404);
        $this->loginAs($ent2)
            ->deleteJson("/api/company/offers/{$offre->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('internship_offers', ['id' => $offre->id]);
    }

    public function test_tc05_le_proprietaire_modifie_puis_supprime_son_offre(): void
    {
        $ent1 = $this->makeUser('entreprise');
        $offre = $this->makeOffer($ent1);

        $this->loginAs($ent1)
            ->putJson("/api/company/offers/{$offre->id}", $this->donnees(['title' => 'Titre modifie']))
            ->assertOk();
        $this->assertDatabaseHas('internship_offers', ['id' => $offre->id, 'title' => 'Titre modifie']);

        $this->loginAs($ent1)->deleteJson("/api/company/offers/{$offre->id}")->assertOk();
        $this->assertDatabaseMissing('internship_offers', ['id' => $offre->id]);
    }

    public function test_tc05_requete_sans_jeton_refusee(): void
    {
        $this->asGuest()->postJson('/api/company/offers', $this->donnees())->assertStatus(401);
    }
}
