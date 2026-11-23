<?php

namespace App\Http\Controllers;

use App\Models\InternshipOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InternshipOfferController extends Controller
{
    public function index(Request $request)
    {
        if (!$this->isCompany($request)) {
            return $this->forbidden();
        }

        return response()->json([
            'success' => true,
            'offers' => $request->user()->internshipOffers()
                ->withAcceptedApplicationsCount()
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        if (!$this->isCompany($request)) {
            return $this->forbidden();
        }

        $validator = Validator::make($request->all(), $this->rules());
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $offer = $request->user()->internshipOffers()->create($validator->validated());
        $offer->loadCount([
            'applications as accepted_applications_count' => function ($query) {
                $query->where('status', 'accepted');
            },
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Offre de stage créée avec succès.',
            'offer' => $offer,
        ], 201);
    }

    public function update(Request $request, InternshipOffer $internshipOffer)
    {
        if (!$this->isCompany($request)) {
            return $this->forbidden();
        }

        $validator = Validator::make($request->all(), $this->rules());
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        return DB::transaction(function () use ($request, $internshipOffer, $validator) {
            $lockedOffer = InternshipOffer::whereKey($internshipOffer->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedOffer || $lockedOffer->company_id !== $request->user()->id) {
                return $this->notFound();
            }

            $acceptedCount = $lockedOffer->applications()
                ->where('status', 'accepted')
                ->count();

            if ($validator->validated()['positions'] < $acceptedCount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le nombre total de places ne peut pas être inférieur au nombre de candidatures acceptées.',
                    'errors' => [
                        'positions' => ['Le nombre de places doit être au moins égal au nombre de candidatures acceptées.'],
                    ],
                ], 422);
            }

            $lockedOffer->update($validator->validated());
            $lockedOffer->loadCount([
                'applications as accepted_applications_count' => function ($query) {
                    $query->where('status', 'accepted');
                },
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Offre de stage mise à jour avec succès.',
                'offer' => $lockedOffer,
            ]);
        });
    }

    public function destroy(Request $request, InternshipOffer $internshipOffer)
    {
        if (!$this->isCompany($request)) {
            return $this->forbidden();
        }

        if ($internshipOffer->company_id !== $request->user()->id) {
            return $this->notFound();
        }

        $internshipOffer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Offre de stage supprimée.',
        ]);
    }

    private function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'domain' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'duration' => 'required|string|max:100',
            'location' => 'required|string|max:255',
            'work_mode' => 'required|in:onsite,hybrid,remote',
            'required_skills' => 'nullable|array|max:20',
            'required_skills.*' => 'required|string|max:100',
            'positions' => 'required|integer|min:1|max:500',
            'deadline' => 'nullable|date|after_or_equal:today',
            'status' => 'required|in:open,closed',
        ];
    }

    private function isCompany(Request $request): bool
    {
        return $request->user()->role === 'entreprise';
    }

    private function forbidden()
    {
        return response()->json([
            'success' => false,
            'message' => 'Seules les entreprises peuvent gérer leurs offres de stage.',
        ], 403);
    }

    private function notFound()
    {
        return response()->json([
            'success' => false,
            'message' => 'Offre de stage non trouvée.',
        ], 404);
    }
}
