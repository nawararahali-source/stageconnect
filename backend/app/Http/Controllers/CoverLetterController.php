<?php

namespace App\Http\Controllers;

use App\Models\InternshipOffer;
use App\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CoverLetterController extends Controller
{
    public function __construct(private AIService $aiService)
    {
    }

    public function generate(Request $request, InternshipOffer $offer)
    {
        $student = $request->user();
        if ($student->role !== 'etudiant') {
            return response()->json([
                'success' => false,
                'message' => 'La génération de lettre de motivation est réservée aux étudiants.',
            ], 403);
        }

        $student->loadMissing(['profile', 'skills']);
        if (!$student->profile) {
            return response()->json([
                'success' => false,
                'message' => 'Complétez votre profil étudiant avant de générer une lettre de motivation.',
            ], 400);
        }

        $offer->loadMissing('company.profile');
        $company = $offer->company;
        $companyName = $company && $company->profile && $company->profile->nom_entreprise
            ? $company->profile->nom_entreprise
            : ($company ? trim($company->first_name . ' ' . $company->last_name) : 'Entreprise');

        $studentData = [
            'prenom' => $student->first_name,
            'nom' => $student->last_name,
            'formation' => $student->profile->formation,
            'specialite' => $student->profile->specialite,
            'niveau' => $student->profile->niveau,
            'competences' => $student->skills->map(function ($skill) {
                return [
                    'nom' => $skill->name,
                    'niveau' => $skill->level,
                ];
            })->values()->all(),
            'experiences' => $student->profile->experiences ?? [],
            'projets' => $student->profile->projects ?? [],
        ];

        $offerData = [
            'titre' => $offer->title,
            'entreprise' => $companyName,
            'description' => $offer->description,
            'competences_demandees' => $offer->required_skills ?? [],
        ];

        try {
            $letter = $this->aiService->generateCoverLetter($studentData, $offerData);

            return response()->json([
                'success' => true,
                'message' => 'Lettre de motivation générée avec succès.',
                'cover_letter' => $letter,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Cover letter generation failed', [
                'student_id' => $student->id,
                'offer_id' => $offer->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $exception instanceof \App\Exceptions\AiUserMessageException ? $exception->getMessage() : 'Impossible de générer la lettre. Veuillez réessayer.',
            ], 503);
        }
    }
}
