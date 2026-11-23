<?php

namespace Tests\Feature;

use App\Models\Cv;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileCompletionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_profile_api_returns_completion_percentage_and_missing_items(): void
    {
        $student = $this->createStudent();
        Profile::create([
            'user_id' => $student->id,
            'formation' => 'Licence DSI',
            'specialite' => 'Développement',
            'niveau' => 'L3',
            'telephone' => '12345678',
            'ville' => 'Tunis',
            'bio' => 'Étudiante en informatique.',
            'experiences' => [['poste' => 'Stagiaire']],
            'projects' => [['nom' => 'Application web']],
            'languages' => [['nom' => 'Français', 'niveau' => 'Avancé']],
            'photo_path' => 'profile-photos/profile.jpg',
            'github' => 'https://github.com/student',
        ]);
        foreach (['PHP', 'Laravel', 'MySQL'] as $skill) {
            $student->skills()->create(['name' => $skill, 'level' => 'avancé']);
        }
        Cv::create([
            'user_id' => $student->id,
            'file_path' => 'cvs/cv.pdf',
            'file_name' => 'cv.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ]);

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('completion_percentage', 100)
            ->assertJsonPath('missing_items', [])
            ->assertJsonPath('profile.completion_percentage', 100)
            ->assertJsonPath('profile.missing_items', []);
    }

    public function test_profile_without_optional_information_reports_each_missing_item(): void
    {
        $student = $this->createStudent();
        Profile::create([
            'user_id' => $student->id,
            'formation' => 'Licence DSI',
            'specialite' => 'Développement',
            'niveau' => 'L3',
        ]);

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('completion_percentage', 9)
            ->assertJsonPath('missing_items', [
                'Ajoutez votre numéro de téléphone',
                'Indiquez votre ville',
                'Rédigez une courte biographie',
                'Ajoutez au moins 3 compétences',
                'Ajoutez une expérience',
                'Ajoutez un projet',
                'Ajoutez une langue',
                'Ajoutez un CV au format PDF',
                'Ajoutez une photo de profil',
                'Ajoutez un lien LinkedIn ou GitHub',
            ]);
    }

    private function createStudent(): User
    {
        return User::create([
            'first_name' => 'Profile',
            'last_name' => 'Completion',
            'email' => uniqid('profile-completion-', true) . '@example.test',
            'password' => Hash::make('password'),
            'role' => 'etudiant',
        ]);
    }
}
