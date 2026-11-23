<?php

namespace App\Http\Controllers;

use App\Models\InternshipOffer;
use App\Services\MatchingService;
use Illuminate\Http\Request;

class MatchingController extends Controller
{
    private MatchingService $matchingService;

    public function __construct(MatchingService $matchingService)
    {
        $this->matchingService = $matchingService;
    }

    /**
     * Get matching scores for applications to a specific offer.
     */
    public function getOfferMatching(Request $request, InternshipOffer $offer)
    {
        if (!$this->isCompany($request)) {
            return $this->forbidden('Seules les entreprises peuvent consulter les scores de matching.');
        }

        // Verify the offer belongs to this company
        if ((int) $offer->company_id !== (int) $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Offre non trouvée ou vous n\'avez pas les droits.',
            ], 404);
        }

        $results = $this->matchingService->calculateMatchingForOffer($offer);

        return response()->json([
            'success' => true,
            'offer_id' => $offer->id,
            'offer_title' => $offer->title,
            'matching_results' => $results,
        ]);
    }

    private function isCompany(Request $request): bool
    {
        return $request->user()->role === 'entreprise';
    }

    private function forbidden(string $message = 'Accès non autorisé.')
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 403);
    }
}
