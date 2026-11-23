<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\InternshipOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ApplicationController extends Controller
{
    /**
     * Display all open and non-expired offers for students.
     */
    public function indexOffers(Request $request)
    {
        if (!$this->isStudent($request)) {
            return $this->forbidden('Seuls les étudiants peuvent consulter les offres de stage.');
        }

        $offers = InternshipOffer::with('company')
            ->withAcceptedApplicationsCount()
            ->where('status', 'open')
            ->where(function ($query) {
                $query->whereNull('deadline')
                      ->orWhere('deadline', '>=', now()->toDateString());
            })
            ->whereRaw(
                '(select count(*) from applications where applications.internship_offer_id = internship_offers.id and applications.status = ?) < internship_offers.positions',
                ['accepted']
            )
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'offers' => $offers,
        ]);
    }

    /**
     * Apply to an internship offer.
     */
    public function apply(Request $request, InternshipOffer $offer)
    {
        if (!$this->isStudent($request)) {
            return $this->forbidden('Seuls les étudiants peuvent postuler aux offres de stage.');
        }

        return DB::transaction(function () use ($request, $offer) {
            $lockedOffer = InternshipOffer::whereKey($offer->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOffer->status !== 'open') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette offre de stage n\'est plus disponible.',
                ], 400);
            }

            if ($lockedOffer->deadline && $lockedOffer->deadline < now()->toDateString()) {
                return response()->json([
                    'success' => false,
                    'message' => 'La date limite de candidature est dépassée.',
                ], 400);
            }

            $existingApplication = Application::where('student_id', $request->user()->id)
                ->where('internship_offer_id', $lockedOffer->id)
                ->first();

            if ($existingApplication) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous avez déjà postulé à cette offre.',
                ], 409);
            }

            $acceptedCount = $lockedOffer->applications()
                ->where('status', 'accepted')
                ->count();

            if ($acceptedCount >= $lockedOffer->positions) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette offre n\'a plus de place disponible.',
                ], 409);
            }

            $application = $lockedOffer->applications()->create([
                'student_id' => $request->user()->id,
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Candidature envoyée avec succès.',
                'application' => $application,
            ], 201);
        });
    }

    /**
     * Display student's own applications.
     */
    public function indexStudentApplications(Request $request)
    {
        if (!$this->isStudent($request)) {
            return $this->forbidden('Seuls les étudiants peuvent consulter leurs candidatures.');
        }

        $applications = Application::with([
            'internshipOffer' => function ($query) {
                $query->withAcceptedApplicationsCount();
            },
            'internshipOffer.company',
            'student.profile'
        ])
            ->where('student_id', $request->user()->id)
            ->latest('applied_at')
            ->get();

        return response()->json([
            'success' => true,
            'applications' => $applications,
        ]);
    }

    /**
     * Display company's received applications.
     */
    public function indexCompanyApplications(Request $request)
    {
        if (!$this->isCompany($request)) {
            return $this->forbidden('Seules les entreprises peuvent consulter les candidatures reçues.');
        }

        // Get offers belonging to this company
        $offerIds = InternshipOffer::where('company_id', $request->user()->id)
            ->pluck('id');

        $applications = Application::with([
            'internshipOffer' => function ($query) {
                $query->withAcceptedApplicationsCount();
            },
            'internshipOffer.company',
            'student.profile',
            'student.cvs'
        ])
            ->whereIn('internship_offer_id', $offerIds)
            ->latest('applied_at')
            ->get();

        return response()->json([
            'success' => true,
            'applications' => $applications,
        ]);
    }

    /**
     * Update application status (accept/reject).
     */
    public function updateStatus(Request $request, Application $application)
    {
        if (!$this->isCompany($request)) {
            return $this->forbidden('Seules les entreprises peuvent modifier le statut des candidatures.');
        }

        // Verify this application belongs to company's offers
        $offer = InternshipOffer::where('id', $application->internship_offer_id)
            ->where('company_id', $request->user()->id)
            ->first();

        if (!$offer) {
            return response()->json([
                'success' => false,
                'message' => 'Candidature non trouvée ou vous n\'avez pas les droits.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:accepted,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        return DB::transaction(function () use ($request, $application, $validator) {
            $offer = InternshipOffer::whereKey($application->internship_offer_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($offer->company_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Candidature non trouvée ou vous n\'avez pas les droits.',
                ], 404);
            }

            $lockedApplication = Application::whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();
            $newStatus = $validator->validated()['status'];

            if ($newStatus === 'accepted' && $lockedApplication->status !== 'accepted') {
                $acceptedCount = $offer->applications()
                    ->where('status', 'accepted')
                    ->count();

                if ($acceptedCount >= $offer->positions) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Toutes les places de cette offre sont déjà attribuées.',
                    ], 409);
                }
            }

            $lockedApplication->update(['status' => $newStatus]);

            return response()->json([
                'success' => true,
                'message' => 'Statut de la candidature mis à jour avec succès.',
                'application' => $lockedApplication->fresh(),
            ]);
        });
    }

    private function isStudent(Request $request): bool
    {
        return $request->user()->role === 'etudiant';
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
