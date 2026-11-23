<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Services\AIService;
use Tests\ApiTestCase;

/**
 * TC-13 : lettre de motivation et analyse de profil (EF-10).
 * Le service Gemini est simule : aucun appel reseau, aucun quota consomme.
 */
class AiFeaturesTest extends ApiTestCase
{
    private function etudiantAvecProfil()
    {
        $etu = $this->makeUser('etudiant');
        Profile::create([
            'user_id' => $etu->id,
            'formation' => 'Licence Informatique',
            'specialite' => 'DSI',
            'niveau' => 'Licence 3',
        ]);

        return $etu;
    }

    private function simulerGemini(): void
    {
        $this->mock(AIService::class, function ($mock) {
            $mock->shouldReceive('generateCoverLetter')->andReturn('Madame, Monsieur, ...');
            $mock->shouldReceive('analyzeProfile')->andReturn(['analysis' => 'Profil solide']);
        });
    }

    private function simulerPanneGemini(string $message): void
    {
        $this->mock(AIService::class, function ($mock) use ($message) {
            $mock->shouldReceive('generateCoverLetter')->andThrow(new \Exception($message));
            $mock->shouldReceive('analyzeProfile')->andThrow(new \Exception($message));
        });
    }

    // ---------------- lettre de motivation ----------------
    public function test_tc13_lettre_generee_pour_un_etudiant_avec_profil(): void
    {
        $this->simulerGemini();
        $offre = $this->makeOffer($this->makeUser('entreprise'));

        $this->loginAs($this->etudiantAvecProfil())
            ->postJson("/api/offers/{$offre->id}/cover-letter")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('cover_letter', 'Madame, Monsieur, ...');
    }

    public function test_tc13_lettre_refusee_sans_profil(): void
    {
        $this->simulerGemini();
        $offre = $this->makeOffer($this->makeUser('entreprise'));

        $this->loginAs($this->makeUser('etudiant'))
            ->postJson("/api/offers/{$offre->id}/cover-letter")
            ->assertStatus(400);
    }

    public function test_tc13_lettre_refusee_pour_une_entreprise(): void
    {
        $this->simulerGemini();
        $ent = $this->makeUser('entreprise');
        $offre = $this->makeOffer($ent);

        $this->loginAs($ent)->postJson("/api/offers/{$offre->id}/cover-letter")->assertStatus(403);
    }

    public function test_tc13_panne_de_gemini_renvoie_503_pour_la_lettre(): void
    {
        $this->simulerPanneGemini('Service indisponible');
        $offre = $this->makeOffer($this->makeUser('entreprise'));

        $this->loginAs($this->etudiantAvecProfil())
            ->postJson("/api/offers/{$offre->id}/cover-letter")
            ->assertStatus(503)
            ->assertJsonPath('success', false);
    }

    /**
     * Anomalie probable : le message de l'exception est renvoye tel quel au client
     * (details techniques exposes).
     */
    public function test_tc13_lettre_n_expose_pas_les_details_techniques(): void
    {
        $this->simulerPanneGemini('DETAIL_TECHNIQUE_CONFIDENTIEL');
        $offre = $this->makeOffer($this->makeUser('entreprise'));

        $reponse = $this->loginAs($this->etudiantAvecProfil())
            ->postJson("/api/offers/{$offre->id}/cover-letter");

        $this->assertStringNotContainsString('DETAIL_TECHNIQUE_CONFIDENTIEL', $reponse->getContent());
    }

    // ---------------- analyse de profil ----------------
    public function test_tc13_analyse_de_profil_pour_un_etudiant_avec_profil(): void
    {
        $this->simulerGemini();

        $this->loginAs($this->etudiantAvecProfil())
            ->postJson('/api/profile/analyze')
            ->assertOk()
            ->assertJsonPath('analysis', 'Profil solide');
    }

    public function test_tc13_analyse_refusee_sans_profil(): void
    {
        $this->simulerGemini();

        $this->loginAs($this->makeUser('etudiant'))->postJson('/api/profile/analyze')->assertStatus(400);
    }

    public function test_tc13_analyse_refusee_pour_une_entreprise(): void
    {
        $this->simulerGemini();

        $this->loginAs($this->makeUser('entreprise'))->postJson('/api/profile/analyze')->assertStatus(403);
    }

    public function test_tc13_panne_de_gemini_renvoie_500_pour_l_analyse(): void
    {
        $this->simulerPanneGemini('Service indisponible');

        $this->loginAs($this->etudiantAvecProfil())
            ->postJson('/api/profile/analyze')
            ->assertStatus(500)
            ->assertJsonPath('success', false);
    }

    /** Anomalie probable : meme exposition du message d'exception pour l'analyse. */
    public function test_tc13_analyse_n_expose_pas_les_details_techniques(): void
    {
        $this->simulerPanneGemini('DETAIL_TECHNIQUE_CONFIDENTIEL');

        $reponse = $this->loginAs($this->etudiantAvecProfil())->postJson('/api/profile/analyze');

        $this->assertStringNotContainsString('DETAIL_TECHNIQUE_CONFIDENTIEL', $reponse->getContent());
    }
}
