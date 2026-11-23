<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\InternshipOffer;
use App\Services\MatchingService;
use Illuminate\Http\Request;

class DashboardStatsController extends Controller
{
    public function __construct(private MatchingService $matchingService)
    {
    }

    public function company(Request $request)
    {
        if ($request->user()->role !== 'entreprise') {
            return response()->json([
                'success' => false,
                'message' => 'Seules les entreprises peuvent consulter ces statistiques.',
            ], 403);
        }

        $offers = InternshipOffer::where('company_id', $request->user()->id)
            ->withCount('applications')
            ->get();
        $offerIds = $offers->pluck('id');
        $statusCounts = Application::whereIn('internship_offer_id', $offerIds)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $applicationsByOffer = $offers->map(function (InternshipOffer $offer) {
            return [
                'offer_id' => $offer->id,
                'offer_title' => $offer->title,
                'count' => (int) $offer->applications_count,
            ];
        })->values();

        $scoreBands = [
            ['label' => '0-25', 'min' => 0, 'max' => 25, 'count' => 0],
            ['label' => '25-50', 'min' => 25, 'max' => 50, 'count' => 0],
            ['label' => '50-75', 'min' => 50, 'max' => 75, 'count' => 0],
            ['label' => '75-100', 'min' => 75, 'max' => 100, 'count' => 0],
        ];

        foreach ($offers as $offer) {
            foreach ($this->matchingService->calculateMatchingForOffer($offer) as $result) {
                $score = (float) $result['score'];
                foreach ($scoreBands as $index => $band) {
                    $isLastBand = $index === count($scoreBands) - 1;
                    if ($score >= $band['min']
                        && ($score < $band['max'] || $isLastBand && $score <= $band['max'])) {
                        $scoreBands[$index]['count']++;
                        break;
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'applications_by_status' => [
                'pending' => (int) ($statusCounts['pending'] ?? 0),
                'accepted' => (int) ($statusCounts['accepted'] ?? 0),
                'rejected' => (int) ($statusCounts['rejected'] ?? 0),
            ],
            'applications_by_offer' => $applicationsByOffer,
            'score_distribution' => $scoreBands,
        ]);
    }

    public function student(Request $request)
    {
        if ($request->user()->role !== 'etudiant') {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les étudiants peuvent consulter ces statistiques.',
            ], 403);
        }

        $statusCounts = Application::where('student_id', $request->user()->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'success' => true,
            'applications_by_status' => [
                'pending' => (int) ($statusCounts['pending'] ?? 0),
                'accepted' => (int) ($statusCounts['accepted'] ?? 0),
                'rejected' => (int) ($statusCounts['rejected'] ?? 0),
            ],
        ]);
    }
}
