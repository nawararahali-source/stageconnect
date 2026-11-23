<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Conversation;
use App\Models\InternshipOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConversationMessagingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_only_application_participants_can_access_a_conversation(): void
    {
        [$student, $company, $application] = $this->createApplicationParticipants();
        $thirdParty = $this->createUser('etudiant');
        $conversation = $this->createConversation($student, $company, $application);

        $this->actingAs($thirdParty, 'sanctum')
            ->getJson("/api/conversations/{$conversation->id}/messages")
            ->assertForbidden();

        $this->actingAs($thirdParty, 'sanctum')
            ->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Intrusion'])
            ->assertForbidden();

        $this->actingAs($thirdParty, 'sanctum')
            ->postJson("/api/applications/{$application->id}/conversation")
            ->assertForbidden();

        $this->actingAs($thirdParty, 'sanctum')
            ->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonCount(0, 'conversations');
    }

    public function test_application_participant_can_create_and_reuse_a_conversation(): void
    {
        [$student, $company, $application] = $this->createApplicationParticipants();

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/applications/{$application->id}/conversation")
            ->assertCreated()
            ->assertJsonPath('conversation.student_id', $student->id)
            ->assertJsonPath('conversation.company_id', $company->id);

        $this->actingAs($company, 'sanctum')
            ->postJson("/api/applications/{$application->id}/conversation")
            ->assertOk();

        $this->assertSame(1, Conversation::where('application_id', $application->id)->count());
    }

    public function test_participant_can_send_a_message(): void
    {
        [$student, $company, $application] = $this->createApplicationParticipants();
        $conversation = $this->createConversation($student, $company, $application);

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Bonjour'])
            ->assertCreated()
            ->assertJsonPath('message.body', 'Bonjour')
            ->assertJsonPath('message.sender_id', $student->id);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $student->id,
            'body' => 'Bonjour',
        ]);

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/conversations/{$conversation->id}/messages", ['body' => str_repeat('a', 2001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }

    public function test_reading_messages_marks_received_messages_as_read(): void
    {
        [$student, $company, $application] = $this->createApplicationParticipants();
        $conversation = $this->createConversation($student, $company, $application);
        $receivedMessage = $conversation->messages()->create([
            'sender_id' => $company->id,
            'body' => 'Bonjour',
        ]);
        $conversation->messages()->create([
            'sender_id' => $student->id,
            'body' => 'Réponse',
        ]);

        $this->actingAs($student, 'sanctum')
            ->getJson("/api/conversations/{$conversation->id}/messages")
            ->assertOk()
            ->assertJsonCount(2, 'messages.data');

        $this->assertNotNull($receivedMessage->fresh()->read_at);
        $this->assertNull($conversation->messages()->where('sender_id', $student->id)->first()->read_at);
    }

    public function test_conversation_list_includes_last_message_unread_count_and_other_participant(): void
    {
        [$student, $company, $application] = $this->createApplicationParticipants();
        $conversation = $this->createConversation($student, $company, $application);
        $conversation->messages()->create([
            'sender_id' => $company->id,
            'body' => 'Bonjour',
        ]);

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonPath('conversations.0.other_participant.name', 'Messaging Test')
            ->assertJsonPath('conversations.0.offer_title', 'Stage de messagerie')
            ->assertJsonPath('conversations.0.last_message.body', 'Bonjour')
            ->assertJsonPath('conversations.0.unread_count', 1);
    }

    public function test_unread_count_only_includes_messages_received_by_the_user(): void
    {
        [$student, $company, $application] = $this->createApplicationParticipants();
        $conversation = $this->createConversation($student, $company, $application);
        $conversation->messages()->create(['sender_id' => $company->id, 'body' => 'Pour étudiant']);
        $conversation->messages()->create(['sender_id' => $student->id, 'body' => 'Pour entreprise']);

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/messages/unread-count')
            ->assertOk()
            ->assertJsonPath('unread_count', 1);
    }

    private function createApplicationParticipants(): array
    {
        $student = $this->createUser('etudiant');
        $company = $this->createUser('entreprise');
        $offer = $company->internshipOffers()->create([
            'title' => 'Stage de messagerie',
            'domain' => 'Informatique',
            'description' => 'Description du stage.',
            'duration' => '3 mois',
            'location' => 'Tunis',
            'work_mode' => 'hybrid',
            'required_skills' => ['Laravel'],
            'positions' => 1,
            'deadline' => null,
            'status' => 'open',
        ]);
        $application = $offer->applications()->create([
            'student_id' => $student->id,
            'status' => 'pending',
        ]);

        return [$student, $company, $application];
    }

    private function createConversation(User $student, User $company, Application $application): Conversation
    {
        return Conversation::create([
            'application_id' => $application->id,
            'student_id' => $student->id,
            'company_id' => $company->id,
        ]);
    }

    private function createUser(string $role): User
    {
        return User::create([
            'first_name' => 'Messaging',
            'last_name' => 'Test',
            'email' => uniqid('messaging-test-', true) . '@example.test',
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }
}
