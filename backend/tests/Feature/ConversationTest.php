<?php

namespace Tests\Feature;

use Tests\ApiTestCase;

/** TC-14 : messagerie liee a une candidature (EF-11). */
class ConversationTest extends ApiTestCase
{
    private function contexte(): array
    {
        $ent = $this->makeUser('entreprise');
        $etu = $this->makeUser('etudiant');
        $offre = $this->makeOffer($ent);
        $candidature = $this->makeApplication($etu, $offre);

        return [$ent, $etu, $candidature];
    }

    private function creerConversation($acteur, $candidature)
    {
        return $this->loginAs($acteur)->postJson("/api/applications/{$candidature->id}/conversation");
    }

    public function test_tc14_une_conversation_par_candidature(): void
    {
        [, $etu, $candidature] = $this->contexte();

        $this->creerConversation($etu, $candidature)->assertStatus(201);
        $this->creerConversation($etu, $candidature)->assertStatus(200);

        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_tc14_valeurs_limites_du_message(): void
    {
        [, $etu, $candidature] = $this->contexte();
        $id = $this->creerConversation($etu, $candidature)->json('conversation.id');
        $url = "/api/conversations/{$id}/messages";

        $this->loginAs($etu)->postJson($url, ['body' => ''])->assertStatus(422);
        $this->loginAs($etu)->postJson($url, ['body' => str_repeat('a', 2000)])->assertStatus(201);
        $this->loginAs($etu)->postJson($url, ['body' => str_repeat('a', 2001)])->assertStatus(422);
    }

    public function test_tc14_un_tiers_n_a_pas_acces_a_la_conversation(): void
    {
        [, $etu, $candidature] = $this->contexte();
        $tiers = $this->makeUser('etudiant');
        $id = $this->creerConversation($etu, $candidature)->json('conversation.id');

        $this->loginAs($tiers)->getJson("/api/conversations/{$id}/messages")->assertStatus(403);
        $this->loginAs($tiers)->postJson("/api/conversations/{$id}/messages", ['body' => 'Intrus'])->assertStatus(403);
    }

    public function test_tc14_lecture_par_le_destinataire_et_compteur_de_non_lus(): void
    {
        [$ent, $etu, $candidature] = $this->contexte();
        $id = $this->creerConversation($etu, $candidature)->json('conversation.id');
        $url = "/api/conversations/{$id}/messages";

        $this->loginAs($etu)->postJson($url, ['body' => 'Bonjour'])->assertStatus(201);
        $this->loginAs($etu)->postJson($url, ['body' => 'Etes-vous disponible ?'])->assertStatus(201);

        // L'expediteur n'a aucun message non lu ; le destinataire en a 2.
        $this->loginAs($etu)->getJson('/api/messages/unread-count')->assertOk()->assertJsonPath('unread_count', 0);
        $this->loginAs($ent)->getJson('/api/messages/unread-count')->assertOk()->assertJsonPath('unread_count', 2);

        // La lecture des messages les marque comme lus.
        $this->loginAs($ent)->getJson($url)->assertOk();
        $this->loginAs($ent)->getJson('/api/messages/unread-count')->assertOk()->assertJsonPath('unread_count', 0);
        $this->assertDatabaseMissing('messages', ['conversation_id' => $id, 'read_at' => null]);
    }
}
