<?php

namespace App\Services;

use App\Models\Application;
use App\Models\InternshipOffer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MatchingService
{
    /**
     * Calculate matching scores for all applications to a specific offer.
     */
    public function calculateMatchingForOffer(InternshipOffer $offer): array
    {
        $applications = Application::with(['student.profile', 'student.skills'])
            ->where('internship_offer_id', $offer->id)
            ->get();

        $results = [];

        foreach ($applications as $application) {
            $results[] = $this->calculateSingleMatching($offer, $application);
        }

        // Sort by score descending
        usort($results, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return $results;
    }

    /**
     * Calculate matching score for a single application.
     */
    private function calculateSingleMatching(InternshipOffer $offer, Application $application): array
    {
        $student = $application->student;
        $profile = $student->profile;
        $skills = $student->skills;

        // Initialize criteria with their weights
        $criteria = [
            'skills' => ['weight' => 50, 'applicable' => true, 'score' => 0],
            'domain' => ['weight' => 20, 'applicable' => true, 'score' => 0],
            'projects_experiences' => ['weight' => 15, 'applicable' => true, 'score' => 0],
            'education_level' => ['weight' => 10, 'applicable' => true, 'score' => 0],
            'location' => ['weight' => 5, 'applicable' => true, 'score' => 0],
        ];

        $matchingSkills = [];
        $missingSkills = [];
        $explanation = [];
        $requiredSkills = is_array($offer->required_skills)
            ? $offer->required_skills
            : [];

        // 1. Skills matching (50%)
        if (!empty($requiredSkills)) {
            foreach ($requiredSkills as $requiredSkill) {
                $matched = false;
                $studentSkillLevel = 'débutant';

                foreach ($skills as $skill) {
                    if ($this->skillsMatch($requiredSkill, $skill->name)) {
                        $matched = true;
                        $studentSkillLevel = $skill->level;
                        $matchingSkills[] = [
                            'name' => $skill->name,
                            'level' => $skill->level,
                            'required' => $requiredSkill
                        ];
                        break;
                    }
                }

                if (!$matched) {
                    $missingSkills[] = $requiredSkill;
                }
            }

            if (count($requiredSkills) > 0) {
                $matchedCount = count($matchingSkills);
                $totalRequired = count($requiredSkills);
                
                // Calculate skill score based on matches and levels
                $skillScore = 0;
                foreach ($matchingSkills as $match) {
                    $levelMultiplier = match($match['level']) {
                        'avancé' => 1.0,
                        'intermédiaire' => 0.75,
                        'débutant' => 0.5,
                        default => 0.5
                    };
                    $skillScore += $levelMultiplier;
                }
                
                $criteria['skills']['score'] = ($skillScore / max($totalRequired, 1)) * 50;
                $explanation[] = "Compétences: {$matchedCount}/{$totalRequired} correspondent (score: " . round($criteria['skills']['score'], 1) . "/50)";
            }
        } else {
            $criteria['skills']['applicable'] = false;
            $explanation[] = "Compétences: Aucune exigence spécifiée (critère ignoré)";
        }

        // 2. Domain matching (20%)
        $studentDomains = array_filter(array_merge(
            $profile ? [$profile->specialite, $profile->formation] : [],
            $skills->pluck('name')->all()
        ));

        if ($studentDomains) {
            $offerDomain = $this->normalizeText($offer->domain);

            if (!empty($offerDomain)) {
                if ($this->hasExactDomainMatch($studentDomains, $offerDomain)) {
                    $criteria['domain']['score'] = 20;
                    $explanation[] = "Domaine: Correspondance exacte (20/20)";
                } elseif ($this->hasRelatedDomainMatch($studentDomains, $offerDomain)) {
                    $criteria['domain']['score'] = 15;
                    $explanation[] = "Domaine: Domaine d'études connexe (15/20)";
                } elseif ($this->hasPartialDomainMatch($studentDomains, $offerDomain)) {
                    $criteria['domain']['score'] = 10;
                    $explanation[] = "Domaine: Correspondance partielle (10/20)";
                } else {
                    $criteria['domain']['score'] = 0;
                    $explanation[] = "Domaine: Pas de correspondance (0/20)";
                }
            } else {
                $criteria['domain']['applicable'] = false;
                $explanation[] = "Domaine: Information non renseignée (critère ignoré)";
            }
        } else {
            $criteria['domain']['applicable'] = false;
            $explanation[] = "Domaine: Profil non renseigné (critère ignoré)";
        }

        // 3. Projects and experiences relevance (15%)
        if ($profile) {
            $experiences = $profile->experiences ?? [];
            $projects = $profile->projects ?? [];
            
            $relevantCount = 0;
            $totalItems = count($experiences) + count($projects);
            
            // Analyze experiences
            foreach ($experiences as $exp) {
                if ($this->isRelevantToDomain($exp, $offer->domain)) {
                    $relevantCount++;
                }
            }
            
            // Analyze projects
            foreach ($projects as $project) {
                if ($this->isRelevantToDomain($project, $offer->domain)) {
                    $relevantCount++;
                }
            }
            
            if ($totalItems > 0) {
                $criteria['projects_experiences']['score'] = ($relevantCount / $totalItems) * 15;
                $explanation[] = "Projets/Expériences: {$relevantCount}/{$totalItems} pertinents (score: " . round($criteria['projects_experiences']['score'], 1) . "/15)";
            } else {
                $criteria['projects_experiences']['applicable'] = false;
                $explanation[] = "Projets/Expériences: Aucun renseigné (critère ignoré)";
            }
        } else {
            $criteria['projects_experiences']['applicable'] = false;
            $explanation[] = "Projets/Expériences: Profil non renseigné (critère ignoré)";
        }

        // 4. Education level (10%)
        if ($profile && $profile->niveau) {
            $levelScore = match(strtolower($profile->niveau)) {
                'doctorat', 'phd' => 10,
                'master', 'master 1', 'master 2', 'm1', 'm2' => 8,
                'licence', 'license', 'bachelor', 'l1', 'l2', 'l3' => 6,
                'bac', 'baccalauréat' => 4,
                default => 2
            };
            $criteria['education_level']['score'] = $levelScore;
            $explanation[] = "Niveau d'études: {$profile->niveau} (score: {$levelScore}/10)";
        } else {
            $criteria['education_level']['applicable'] = false;
            $explanation[] = "Niveau d'études: Non renseigné (critère ignoré)";
        }

        // 5. Location matching (5%)
        if ($profile && $profile->ville && $offer->location) {
            $studentCity = strtolower(trim($profile->ville));
            $offerLocation = strtolower(trim($offer->location));
            
            if ($offer->work_mode === 'remote') {
                $criteria['location']['score'] = 5;
                $explanation[] = "Localisation: Télétravail (5/5)";
            } elseif ($studentCity === $offerLocation) {
                $criteria['location']['score'] = 5;
                $explanation[] = "Localisation: Même ville (5/5)";
            } else {
                $criteria['location']['score'] = 0;
                $explanation[] = "Localisation: Ville différente (0/5)";
            }
        } else {
            $criteria['location']['applicable'] = false;
            $explanation[] = "Localisation: Information non renseignée (critère ignoré)";
        }

        // Calculate final score with weight redistribution
        $totalWeight = 0;
        $applicableWeight = 0;
        
        foreach ($criteria as $criterion) {
            $totalWeight += $criterion['weight'];
            if ($criterion['applicable']) {
                $applicableWeight += $criterion['weight'];
            }
        }

        $finalScore = 0;
        if ($applicableWeight > 0) {
            foreach ($criteria as $criterion) {
                if ($criterion['applicable']) {
                    // Redistribute weight proportionally
                    $adjustedWeight = ($criterion['weight'] / $applicableWeight) * 100;
                    $finalScore += ($criterion['score'] / $criterion['weight']) * $adjustedWeight;
                }
            }
        }

        $features = $this->buildFeatures(
            $offer,
            $profile,
            $skills,
            $matchingSkills,
            count($requiredSkills),
            $criteria['domain']['score'] > 0
        );
        $score = round($finalScore, 1);
        $source = 'rules';
        $contributions = $this->buildRuleContributions($criteria, $features, $score);

        $mlUrl = rtrim(config('services.ml_service.url', 'http://127.0.0.1:8001'), '/') . '/predict';

        try {
            Log::info('Sending candidate-offer features to ML matching service.', [
                'url' => $mlUrl,
                'payload' => $features,
                'offer_id' => $offer->id,
                'application_id' => $application->id,
            ]);

            $response = Http::timeout(3)->post($mlUrl, $features);
            if ($response->successful()) {
                $prediction = $response->json();
                $validationError = $this->predictionValidationError($prediction);
                if ($validationError === null) {
                    $score = round((float) $prediction['score'], 1);
                    $contributions = $prediction['contributions'];
                    $source = 'ml';
                } else {
                    Log::warning('ML matching fallback: response payload is invalid.', [
                        'offer_id' => $offer->id,
                        'application_id' => $application->id,
                        'fallback_reason' => 'invalid_response',
                        'status' => $response->status(),
                        'reason' => $validationError,
                    ]);
                }
            } else {
                Log::warning('ML matching fallback: service returned a non-success status.', [
                    'offer_id' => $offer->id,
                    'application_id' => $application->id,
                    'fallback_reason' => 'http_status',
                    'status' => $response->status(),
                    'reason' => $response->body(),
                ]);
            }
        } catch (ConnectionException $exception) {
            $message = $exception->getMessage();
            $isTimeout = stripos($message, 'timeout') !== false
                || stripos($message, 'timed out') !== false;
            Log::warning('ML matching fallback: connection or timeout exception.', [
                'offer_id' => $offer->id,
                'application_id' => $application->id,
                'fallback_reason' => $isTimeout ? 'timeout' : 'connection_error',
                'exception' => get_class($exception),
                'message' => $message,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('ML matching fallback: unexpected prediction exception.', [
                'offer_id' => $offer->id,
                'application_id' => $application->id,
                'fallback_reason' => 'exception',
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);
        }

        return [
            'application_id' => $application->id,
            'student_id' => $student->id,
            'student_name' => $student->first_name . ' ' . $student->last_name,
            'score' => $score,
            'matching_skills' => $matchingSkills,
            'missing_skills' => $missingSkills,
            'explanation' => $explanation,
            'criteria' => $criteria,
            'source' => $source,
            'contributions' => $contributions,
        ];
    }

    /**
     * Construit les caractéristiques numériques attendues par le modèle ML.
     */
    private function buildFeatures(
        InternshipOffer $offer,
        $profile,
        $skills,
        array $matchingSkills,
        int $requiredSkillCount,
        bool $domainMatch
    ): array {
        $experiences = $profile ? ($profile->experiences ?? []) : [];
        $projects = $profile ? ($profile->projects ?? []) : [];
        $matchedCount = count($matchingSkills);
        $skillLevels = array_map(function ($skill) {
            return $this->skillLevelValue($skill['level']);
        }, $matchingSkills);
        $cityMatches = $profile
            && $profile->ville
            && $offer->location
            && $this->normalizeText($profile->ville) === $this->normalizeText($offer->location);

        return [
            'skills_match_ratio' => $requiredSkillCount > 0
                ? $matchedCount / $requiredSkillCount
                : 0.0,
            'nb_skills_matched' => $matchedCount,
            'avg_skill_level' => $matchedCount > 0
                ? array_sum($skillLevels) / $matchedCount
                : 0.0,
            'domain_match' => $domainMatch ? 1 : 0,
            'nb_projects' => count($projects),
            'nb_experiences' => count($experiences),
            'study_level' => $this->studyLevelValue($profile ? $profile->niveau : null),
            'location_match' => $offer->work_mode === 'remote' ? 2 : ($cityMatches ? 1 : 0),
            'tfidf_similarity' => $this->calculateTfidfSimilarity(
                $profile,
                $skills,
                $projects,
                $experiences,
                $offer
            ),
        ];
    }

    /**
     * Évalue le texte du profil et de l'offre avec une similarité TF-IDF cosinus.
     */
    private function calculateTfidfSimilarity(
        $profile,
        $skills,
        array $projects,
        array $experiences,
        InternshipOffer $offer
    ): float {
        $candidateText = implode(' ', array_filter([
            $profile ? $profile->specialite : null,
            $profile ? $profile->formation : null,
            $profile ? $profile->bio : null,
            $skills->pluck('name')->implode(' '),
            $this->portfolioText($projects),
            $this->portfolioText($experiences),
        ]));
        $offerText = implode(' ', array_filter([
            $offer->domain,
            $offer->description,
            is_array($offer->required_skills) ? implode(' ', $offer->required_skills) : '',
        ]));

        $documents = [
            $this->tfidfTerms($candidateText),
            $this->tfidfTerms($offerText),
        ];
        $terms = array_unique(array_merge(array_keys($documents[0]), array_keys($documents[1])));
        if (empty($terms)) {
            return 0.0;
        }

        $vectors = [[], []];
        foreach ($terms as $term) {
            $documentFrequency = (int) isset($documents[0][$term]) + (int) isset($documents[1][$term]);
            $inverseDocumentFrequency = log(3 / (1 + $documentFrequency)) + 1;
            foreach ([0, 1] as $documentIndex) {
                $vectors[$documentIndex][$term] = ($documents[$documentIndex][$term] ?? 0)
                    * $inverseDocumentFrequency;
            }
        }

        $dotProduct = 0.0;
        $candidateNorm = 0.0;
        $offerNorm = 0.0;
        foreach ($terms as $term) {
            $candidateWeight = $vectors[0][$term];
            $offerWeight = $vectors[1][$term];
            $dotProduct += $candidateWeight * $offerWeight;
            $candidateNorm += $candidateWeight ** 2;
            $offerNorm += $offerWeight ** 2;
        }

        if ($candidateNorm === 0.0 || $offerNorm === 0.0) {
            return 0.0;
        }

        return round($dotProduct / (sqrt($candidateNorm) * sqrt($offerNorm)), 6);
    }

    private function tfidfTerms(string $text): array
    {
        $normalized = $this->normalizeText($text);
        $tokens = array_values(array_filter(explode(' ', $normalized), function ($token) {
            return strlen($token) > 1;
        }));
        $terms = [];

        foreach ($tokens as $index => $token) {
            $terms[$token] = ($terms[$token] ?? 0) + 1;
            if (isset($tokens[$index + 1])) {
                $bigram = $token . ' ' . $tokens[$index + 1];
                $terms[$bigram] = ($terms[$bigram] ?? 0) + 1;
            }
        }

        return $terms;
    }

    private function portfolioText(array $items): string
    {
        $parts = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            foreach (['nom', 'poste', 'technologies', 'description'] as $field) {
                if (!empty($item[$field])) {
                    $parts[] = $item[$field];
                }
            }
        }

        return implode(' ', $parts);
    }

    private function skillLevelValue(string $level): float
    {
        return match ($this->normalizeText($level)) {
            'avance', 'advanced' => 3.0,
            'intermediaire', 'intermediate' => 2.0,
            'debutant', 'beginner' => 1.0,
            default => 1.0,
        };
    }

    private function studyLevelValue(?string $level): int
    {
        $normalized = $this->normalizeText($level ?? '');
        if (preg_match('/\b(m[12]|l[123])\b/', $normalized, $matches)) {
            return match ($matches[1]) {
                'l1' => 1,
                'l2' => 2,
                'l3' => 3,
                'm1' => 4,
                'm2' => 5,
            };
        }
        if (str_contains($normalized, 'master')) {
            return 4;
        }
        if (str_contains($normalized, 'licence') || str_contains($normalized, 'bachelor')) {
            return 3;
        }

        return 1;
    }

    private function predictionValidationError($prediction): ?string
    {
        if (!is_array($prediction)) {
            return 'La réponse ne contient pas un objet JSON valide.';
        }

        if (!isset($prediction['score']) || !is_numeric($prediction['score'])) {
            return 'Le champ score est absent ou non numérique.';
        }

        if ((float) $prediction['score'] < 0 || (float) $prediction['score'] > 100) {
            return 'Le score est en dehors de l’intervalle autorisé [0, 100].';
        }

        if (!isset($prediction['contributions']) || !is_array($prediction['contributions'])) {
            return 'Le champ contributions est absent ou n’est pas un objet.';
        }

        foreach ($prediction['contributions'] as $contribution) {
            if (!is_array($contribution)
                || !isset($contribution['valeur'], $contribution['poids_pourcent'], $contribution['contribution'])) {
                return 'Une contribution ne contient pas valeur, poids_pourcent et contribution.';
            }
        }

        return null;
    }

    /**
     * Retourne les contributions du scoring pondéré existant.
     */
    private function buildRuleContributions(array $criteria, array $features, float $score): array
    {
        $applicableWeight = array_sum(array_map(function ($criterion) {
            return $criterion['applicable'] ? $criterion['weight'] : 0;
        }, $criteria));
        $scale = $applicableWeight > 0 ? 100 / $applicableWeight : 0;
        $portfolioContribution = $criteria['projects_experiences']['applicable']
            ? $criteria['projects_experiences']['score'] * $scale
            : 0;
        $portfolioCount = $features['nb_projects'] + $features['nb_experiences'];
        $projectContribution = $portfolioCount > 0
            ? $portfolioContribution * $features['nb_projects'] / $portfolioCount
            : 0;
        $experienceContribution = $portfolioContribution - $projectContribution;
        $contributions = [
            'skills_match_ratio' => $this->weightedCriterionContribution($criteria['skills'], $scale),
            'nb_skills_matched' => 0.0,
            'avg_skill_level' => 0.0,
            'domain_match' => $this->weightedCriterionContribution($criteria['domain'], $scale),
            'nb_projects' => $projectContribution,
            'nb_experiences' => $experienceContribution,
            'study_level' => $this->weightedCriterionContribution($criteria['education_level'], $scale),
            'location_match' => $this->weightedCriterionContribution($criteria['location'], $scale),
            'tfidf_similarity' => 0.0,
        ];
        $totalContribution = array_sum($contributions);

        $result = [];
        foreach ($contributions as $feature => $contribution) {
            $result[$feature] = [
                'valeur' => (float) $features[$feature],
                'poids_pourcent' => $totalContribution > 0
                    ? round(abs($contribution) / $totalContribution * 100, 2)
                    : 0,
                'contribution' => round($contribution, 2),
            ];
        }

        if ($score > 0) {
            $largestContribution = array_search(
                max(array_column($result, 'contribution')),
                array_column($result, 'contribution'),
                true
            );
            $featureNames = array_keys($result);
            if ($largestContribution !== false) {
                $feature = $featureNames[$largestContribution];
                $result[$feature]['contribution'] = round(
                    $result[$feature]['contribution']
                        + $score - array_sum(array_column($result, 'contribution')),
                    2
                );
            }
        }

        return $result;
    }

    private function weightedCriterionContribution(array $criterion, float $scale): float
    {
        return $criterion['applicable']
            ? $criterion['score'] * $scale
            : 0.0;
    }

    /**
     * Check if two skill names match (fuzzy matching).
     */
    private function skillsMatch(string $required, string $student): bool
    {
        $required = $this->normalizeText($required);
        $student = $this->normalizeText($student);

        if ($required === '' || $student === '') {
            return false;
        }

        // Exact match
        if ($required === $student) {
            return true;
        }

        // Partial match (one contains the other)
        if (str_contains($student, $required) || str_contains($required, $student)) {
            return true;
        }

        return false;
    }

    /**
     * Check if an experience or project is relevant to the domain.
     */
    private function isRelevantToDomain(array $item, string $offerDomain): bool
    {
        $text = '';

        if (isset($item['description'])) {
            $text .= ' ' . $item['description'];
        }
        if (isset($item['poste'])) {
            $text .= ' ' . $item['poste'];
        }
        if (isset($item['nom'])) {
            $text .= ' ' . $item['nom'];
        }
        if (isset($item['technologies'])) {
            $text .= ' ' . $item['technologies'];
        }

        $normalizedText = ' ' . $this->normalizeText($text) . ' ';

        foreach ($this->domainKeywords($offerDomain) as $keyword) {
            if (str_contains($normalizedText, ' ' . $keyword . ' ')) {
                return true;
            }
        }

        return false;
    }

    private function hasExactDomainMatch(array $studentDomains, string $offerDomain): bool
    {
        foreach ($studentDomains as $studentDomain) {
            if ($this->normalizeText($studentDomain) === $offerDomain) {
                return true;
            }
        }

        return false;
    }

    private function hasRelatedDomainMatch(array $studentDomains, string $offerDomain): bool
    {
        $offerFamily = $this->domainFamily($offerDomain);
        if ($offerFamily === null) {
            return false;
        }

        foreach ($studentDomains as $studentDomain) {
            $studentFamily = $this->domainFamily($studentDomain);
            if ($studentFamily === $offerFamily
                || in_array($studentFamily, ['accounting', 'finance'], true)
                    && in_array($offerFamily, ['accounting', 'finance'], true)) {
                return true;
            }
        }

        return false;
    }

    private function hasPartialDomainMatch(array $studentDomains, string $offerDomain): bool
    {
        $offerWords = array_filter(explode(' ', $offerDomain), function ($word) {
            return strlen($word) > 3;
        });

        foreach ($studentDomains as $studentDomain) {
            $studentWords = array_filter(explode(' ', $this->normalizeText($studentDomain)), function ($word) {
                return strlen($word) > 3;
            });

            if (array_intersect($offerWords, $studentWords)) {
                return true;
            }
        }

        return false;
    }

    private function domainFamily(string $domain): ?string
    {
        $normalizedDomain = $this->normalizeText($domain);
        $families = [
            'accounting' => [
                'comptabilite',
                'comptable',
                'compta',
                'accounting',
                'accountant',
                'audit comptable',
                'fiscalite',
                'fiscal',
            ],
            'finance' => [
                'finance',
                'financier',
                'financiere',
                'financial',
                'banque',
                'bancaire',
                'tresorerie',
                'budget',
            ],
        ];

        foreach ($families as $family => $terms) {
            foreach ($terms as $term) {
                if ($this->containsPhrase($normalizedDomain, $term)) {
                    return $family;
                }
            }
        }

        return null;
    }

    private function domainKeywords(string $domain): array
    {
        $normalizedDomain = $this->normalizeText($domain);
        $keywords = array_filter(explode(' ', $normalizedDomain), function ($word) {
            return strlen($word) > 3;
        });

        $family = $this->domainFamily($normalizedDomain);
        if ($family === 'accounting') {
            $keywords = array_merge($keywords, [
                'comptabilite',
                'comptable',
                'compta',
                'accounting',
                'accountant',
                'audit',
                'fiscalite',
                'fiscal',
            ]);
        }

        return array_values(array_unique($keywords));
    }

    private function containsPhrase(string $text, string $phrase): bool
    {
        return str_contains(' ' . $text . ' ', ' ' . $this->normalizeText($phrase) . ' ');
    }

    private function normalizeText(string $text): string
    {
        $ascii = Str::ascii(mb_strtolower(trim($text)));
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $ascii) ?? '');
    }
}
