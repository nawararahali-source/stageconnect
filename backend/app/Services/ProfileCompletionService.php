<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\User;

class ProfileCompletionService
{
    /**
     * Calcule la complétion d'un profil étudiant sur onze critères de poids égal.
     */
    public function calculate(User $user, ?Profile $profile): array
    {
        $skillsCount = $user->skills()->count();
        $hasPdfCv = $user->cvs()
            ->where(function ($query) {
                $query->where('mime_type', 'application/pdf')
                    ->orWhere('file_name', 'like', '%.pdf')
                    ->orWhere('file_path', 'like', '%.pdf');
            })
            ->exists();

        $criteria = [
            [!empty(trim((string) ($profile?->formation))), 'Ajoutez votre formation'],
            [!empty(trim((string) ($profile?->telephone))), 'Ajoutez votre numéro de téléphone'],
            [!empty(trim((string) ($profile?->ville))), 'Indiquez votre ville'],
            [!empty(trim((string) ($profile?->bio))), 'Rédigez une courte biographie'],
            [$skillsCount >= 3, 'Ajoutez au moins 3 compétences'],
            [!empty($profile?->experiences), 'Ajoutez une expérience'],
            [!empty($profile?->projects), 'Ajoutez un projet'],
            [!empty($profile?->languages), 'Ajoutez une langue'],
            [$hasPdfCv, 'Ajoutez un CV au format PDF'],
            [!empty(trim((string) ($profile?->photo_path))), 'Ajoutez une photo de profil'],
            [
                !empty(trim((string) ($profile?->linkedin)))
                    || !empty(trim((string) ($profile?->github))),
                'Ajoutez un lien LinkedIn ou GitHub',
            ],
        ];

        $missingItems = [];
        foreach ($criteria as [$isComplete, $missingItem]) {
            if (!$isComplete) {
                $missingItems[] = $missingItem;
            }
        }

        return [
            'completion_percentage' => (int) round(
                (count($criteria) - count($missingItems)) / count($criteria) * 100
            ),
            'missing_items' => $missingItems,
        ];
    }
}
