<?php

namespace Tests\Feature;

use Tests\ApiTestCase;

/** TC-01, TC-02, TC-03 : inscription, connexion, deconnexion (EF-01, EF-02). */
class AuthTest extends ApiTestCase
{
    private function donnees(array $surcharge = []): array
    {
        return array_merge([
            'first_name' => 'Nawara',
            'last_name' => 'Rahali',
            'email' => 'nawara@test.local',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'role' => 'etudiant',
        ], $surcharge);
    }

    public function test_tc01_inscription_valide(): void
    {
        $reponse = $this->postJson('/api/register', $this->donnees());

        $reponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.role', 'etudiant')
            ->assertJsonStructure(['token']);
        $this->assertDatabaseHas('users', ['email' => 'nawara@test.local', 'role' => 'etudiant']);
    }

    public function test_tc02a_mot_de_passe_de_5_caracteres_refuse(): void
    {
        $this->postJson('/api/register', $this->donnees(['password' => 'Ab1!c', 'password_confirmation' => 'Ab1!c']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_tc02b_mot_de_passe_de_6_caracteres_accepte(): void
    {
        $this->postJson('/api/register', $this->donnees(['password' => 'Ab1!cd', 'password_confirmation' => 'Ab1!cd']))
            ->assertStatus(201);
    }

    public function test_tc02c_email_deja_utilise_refuse(): void
    {
        $this->postJson('/api/register', $this->donnees())->assertStatus(201);

        $this->postJson('/api/register', $this->donnees())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_tc02d_role_invalide_refuse(): void
    {
        $this->postJson('/api/register', $this->donnees(['role' => 'admin']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    public function test_tc02e_confirmation_du_mot_de_passe_absente_refusee(): void
    {
        $donnees = $this->donnees();
        unset($donnees['password_confirmation']);

        $this->postJson('/api/register', $donnees)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_tc03_connexion_mot_de_passe_errone(): void
    {
        $this->makeUser('etudiant', 'session@test.local');

        $this->postJson('/api/login', ['email' => 'session@test.local', 'password' => 'mauvais'])
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_tc03_connexion_email_vide(): void
    {
        $this->postJson('/api/login', ['email' => '', 'password' => self::PASSWORD])
            ->assertStatus(422);
    }

    public function test_tc03_session_connexion_me_deconnexion(): void
    {
        $this->makeUser('etudiant', 'session@test.local');

        $login = $this->postJson('/api/login', ['email' => 'session@test.local', 'password' => self::PASSWORD]);
        $login->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['token', 'user' => ['id', 'role']]);
        $token = $login->json('token');

        $this->useToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'session@test.local');

        $this->useToken($token)->postJson('/api/logout')->assertOk();

        // Le meme jeton ne doit plus etre accepte apres la deconnexion.
        $this->useToken($token)->getJson('/api/me')->assertStatus(401);
    }
}
