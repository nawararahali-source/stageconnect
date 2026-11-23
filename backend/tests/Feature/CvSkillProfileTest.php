<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\ApiTestCase;

/** TC-10 (CV), TC-11 (profil, photo, competences) : EF-03, EF-04, EF-05. */
class CvSkillProfileTest extends ApiTestCase
{
    // ---------------- TC-10 : CV ----------------
    private function envoyerCv($utilisateur, UploadedFile $fichier)
    {
        return $this->loginAs($utilisateur)->post('/api/cv', ['cv' => $fichier], ['Accept' => 'application/json']);
    }

    public function test_tc10_cv_de_5120_ko_accepte(): void
    {
        Storage::fake('public');
        $etu = $this->makeUser('etudiant');

        $this->envoyerCv($etu, UploadedFile::fake()->create('cv_5120.pdf', 5120, 'application/pdf'))
            ->assertStatus(201);
    }

    public function test_tc10_cv_de_5121_ko_refuse(): void
    {
        Storage::fake('public');
        $etu = $this->makeUser('etudiant');

        $this->envoyerCv($etu, UploadedFile::fake()->create('cv_5121.pdf', 5121, 'application/pdf'))
            ->assertStatus(422);
    }

    public function test_tc10_fichier_image_refuse(): void
    {
        Storage::fake('public');
        $etu = $this->makeUser('etudiant');

        $this->envoyerCv($etu, UploadedFile::fake()->create('image.png', 10, 'image/png'))
            ->assertStatus(422);
    }

    public function test_tc10_un_seul_cv_par_etudiant(): void
    {
        Storage::fake('public');
        $etu = $this->makeUser('etudiant');

        $this->envoyerCv($etu, UploadedFile::fake()->create('premier.pdf', 100, 'application/pdf'))->assertStatus(201);
        $this->envoyerCv($etu, UploadedFile::fake()->create('second.pdf', 100, 'application/pdf'))->assertStatus(201);

        $this->loginAs($etu)->getJson('/api/cv')->assertOk()->assertJsonCount(1, 'cvs');
    }

    public function test_tc10_un_etudiant_ne_peut_pas_supprimer_le_cv_d_un_autre(): void
    {
        Storage::fake('public');
        $etu1 = $this->makeUser('etudiant');
        $etu2 = $this->makeUser('etudiant');
        $reponse = $this->envoyerCv($etu1, UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'))->assertStatus(201);
        $idCv = $reponse->json('cv.id');

        $this->loginAs($etu2)->deleteJson("/api/cv/{$idCv}")->assertStatus(404);

        $this->assertDatabaseHas('cvs', ['id' => $idCv]);
    }

    // ---------------- TC-11 : profil ----------------
    private function profil(array $surcharge = []): array
    {
        return array_merge([
            'formation' => 'Licence Informatique',
            'specialite' => 'Developpement des systemes d\'information',
            'niveau' => 'Licence 3',
        ], $surcharge);
    }

    public function test_tc11_profil_inexistant_renvoie_404(): void
    {
        $etu = $this->makeUser('etudiant');

        $this->loginAs($etu)->getJson('/api/profile')->assertStatus(404);
    }

    public function test_tc11_creation_lecture_et_modification_du_profil(): void
    {
        $etu = $this->makeUser('etudiant');

        $this->loginAs($etu)->postJson('/api/profile', $this->profil())->assertStatus(201);
        $this->assertDatabaseHas('profiles', ['user_id' => $etu->id, 'niveau' => 'Licence 3']);

        $this->loginAs($etu)->getJson('/api/profile')->assertOk();

        $this->loginAs($etu)->putJson('/api/profile', $this->profil(['niveau' => 'Master 1']))->assertOk();
        $this->assertDatabaseHas('profiles', ['user_id' => $etu->id, 'niveau' => 'Master 1']);
    }

    public function test_tc11_profil_sans_formation_refuse(): void
    {
        $etu = $this->makeUser('etudiant');
        $donnees = $this->profil();
        unset($donnees['formation']);

        $this->loginAs($etu)->postJson('/api/profile', $donnees)->assertStatus(422);
    }

    public function test_tc11_photo_valide_acceptee_et_fichier_non_image_refuse(): void
    {
        Storage::fake('public');
        $etu = $this->makeUser('etudiant');
        $this->loginAs($etu)->postJson('/api/profile', $this->profil())->assertStatus(201);

        $this->loginAs($etu)->post('/api/profile/photo',
            ['photo' => UploadedFile::fake()->create('photo.png', 100, 'image/png')],
            ['Accept' => 'application/json'])->assertOk();

        $this->loginAs($etu)->post('/api/profile/photo',
            ['photo' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf')],
            ['Accept' => 'application/json'])->assertStatus(422);
    }

    // ---------------- TC-11 : competences ----------------
    public function test_tc11_ajout_modification_suppression_d_une_competence(): void
    {
        $etu = $this->makeUser('etudiant');

        $creation = $this->loginAs($etu)
            ->postJson('/api/skills', ['name' => 'Laravel', 'level' => 'intermédiaire'])
            ->assertStatus(201);
        $id = $creation->json('skill.id');

        $this->loginAs($etu)->putJson("/api/skills/{$id}", ['name' => 'Laravel 9', 'level' => 'avancé'])->assertOk();
        $this->assertDatabaseHas('skills', ['id' => $id, 'name' => 'Laravel 9', 'level' => 'avancé']);

        $this->loginAs($etu)->getJson('/api/skills')->assertOk()->assertJsonCount(1, 'skills');

        $this->loginAs($etu)->deleteJson("/api/skills/{$id}")->assertOk();
        $this->assertDatabaseMissing('skills', ['id' => $id]);
    }

    public function test_tc11_niveau_de_competence_invalide_refuse(): void
    {
        $etu = $this->makeUser('etudiant');

        $this->loginAs($etu)->postJson('/api/skills', ['name' => 'Laravel', 'level' => 'expert'])
            ->assertStatus(422);
    }

    public function test_tc11_une_competence_d_un_autre_utilisateur_est_introuvable(): void
    {
        $etu1 = $this->makeUser('etudiant');
        $etu2 = $this->makeUser('etudiant');
        $id = $this->loginAs($etu1)
            ->postJson('/api/skills', ['name' => 'PHP', 'level' => 'avancé'])->json('skill.id');

        $this->loginAs($etu2)->putJson("/api/skills/{$id}", ['name' => 'Pirate', 'level' => 'avancé'])->assertStatus(404);
        $this->loginAs($etu2)->deleteJson("/api/skills/{$id}")->assertStatus(404);
    }

    /**
     * Anomalie probable : SkillController::update() passe $request->all() a update()
     * et user_id est dans $fillable : le proprietaire peut etre change.
     */
    public function test_tc11_la_modification_ne_change_pas_le_proprietaire_de_la_competence(): void
    {
        $etu1 = $this->makeUser('etudiant');
        $etu2 = $this->makeUser('etudiant');
        $id = $this->loginAs($etu1)
            ->postJson('/api/skills', ['name' => 'PHP', 'level' => 'avancé'])->json('skill.id');

        $this->loginAs($etu1)->putJson("/api/skills/{$id}", [
            'name' => 'PHP', 'level' => 'avancé', 'user_id' => $etu2->id,
        ]);

        $this->assertDatabaseHas('skills', ['id' => $id, 'user_id' => $etu1->id]);
    }
}
