<?php

namespace App\Http\Controllers;

use App\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfileAnalysisController extends Controller
{
    private AIService $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Analyze student profile with AI
     */
    public function analyze(Request $request)
    {
        $user = $request->user();

        // Verify user is a student
        if ($user->role !== 'etudiant') {
            return response()->json([
                'success' => false,
                'message' => 'Cette fonctionnalité est réservée aux étudiants',
            ], 403);
        }

        // Check if user has a profile
        $profile = $user->profile;
        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Veuillez créer votre profil avant de demander une analyse IA',
            ], 400);
        }

        try {
            // Collect profile data
            $profileData = [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'formation' => $profile->formation,
                'specialite' => $profile->specialite,
                'niveau' => $profile->niveau,
                'bio' => $profile->bio,
                'skills' => $user->skills->map(function ($skill) {
                    return [
                        'name' => $skill->name,
                        'level' => $skill->level,
                    ];
                })->toArray(),
                'experiences' => $profile->experiences ?? [],
                'projects' => $profile->projects ?? [],
                'languages' => $profile->languages ?? [],
            ];

            // Try to extract CV content if available
            $cv = $user->cvs->first();
            if ($cv) {
                $cvContent = $this->extractCvContent($cv);
                if ($cvContent) {
                    $profileData['cv_content'] = $cvContent;
                }
            }

            // Call AI service
            $analysis = $this->aiService->analyzeProfile($profileData);

            return response()->json([
                'success' => true,
                'message' => 'Analyse du profil générée avec succès',
                'analysis' => $analysis['analysis'] ?? null,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'analyse du profil.',
            ], 500);
        }
    }

    /**
     * Extract text content from CV PDF
     * Note: This is a basic implementation. For production, consider using a proper PDF parsing library
     */
    private function extractCvContent($cv): ?string
    {
        try {
            $cvPath = storage_path('app/public/' . $cv->file_path);

            if (!file_exists($cvPath)) {
                return null;
            }

            // For now, we'll return null as PDF text extraction requires additional libraries
            // In production, you could use:
            // - smalot/pdfparser (Composer package)
            // - pdftotext (system command)
            // - Or an external API service
            
            // Placeholder for future implementation
            return null;

        } catch (\Exception $e) {
            \Log::error('CV Extraction Error', [
                'message' => $e->getMessage(),
                'cv_id' => $cv->id
            ]);
            return null;
        }
    }
}
