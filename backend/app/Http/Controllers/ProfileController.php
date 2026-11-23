<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Services\ProfileCompletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    public function __construct(private ProfileCompletionService $profileCompletionService)
    {
    }

    /**
     * Create or update user profile
     */
    public function store(Request $request)
    {
        $user = $request->user();
        
        // Validation rules based on user role
        if ($user->role === 'etudiant') {
            $validator = Validator::make($request->all(), $this->studentProfileRules());
        } else if ($user->role === 'entreprise') {
            $validator = Validator::make($request->all(), [
                'nom_entreprise' => 'required|string|max:255',
                'secteur_activite' => 'required|string|max:255',
                'adresse' => 'required|string|max:500',
                'description' => 'required|string|max:1000',
                'telephone' => 'nullable|string|max:20',
                'ville' => 'nullable|string|max:255',
                'site_web' => 'nullable|url|max:500',
                'linkedin' => 'nullable|url|max:500',
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Rôle utilisateur invalide',
            ], 400);
        }

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create or update profile
        $profile = Profile::updateOrCreate(
            ['user_id' => $user->id],
            $validator->validated()
        );
        $completion = $this->profileCompletionData($user, $profile);
        $profileData = array_merge($profile->toArray(), [
            'photo_url' => $profile->photo_path ? asset('storage/' . $profile->photo_path) : null,
            'experiences' => $profile->experiences ?? [],
            'projects' => $profile->projects ?? [],
            'languages' => $profile->languages ?? [],
        ], $completion);

        return response()->json(array_merge([
            'success' => true,
            'message' => 'Profil créé avec succès',
            'profile' => $profileData,
        ], $completion), 201);
    }

    /**
     * Get user profile
     */
    public function show(Request $request)
    {
        $profile = $request->user()->profile;

        if (!$profile) {
            $completion = $this->profileCompletionData($request->user(), null);

            return response()->json(array_merge([
                'success' => false,
                'message' => 'Profil non trouvé',
            ], $completion), 404);
        }

        $profileData = $profile->toArray();
        $profileData['photo_url'] = $profile->photo_path ? asset('storage/' . $profile->photo_path) : null;
        $profileData['experiences'] = $profile->experiences ?? [];
        $profileData['projects'] = $profile->projects ?? [];
        $profileData['languages'] = $profile->languages ?? [];
        $completion = $this->profileCompletionData($request->user(), $profile);
        $profileData = array_merge($profileData, $completion);

        return response()->json(array_merge([
            'success' => true,
            'profile' => $profileData,
        ], $completion));
    }

    /**
     * Update user profile
     */
    public function update(Request $request)
    {
        $user = $request->user();
        $profile = $user->profile;

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun profil trouvé. Veuillez créer votre profil d\'abord.',
            ], 404);
        }

        // Validation rules based on user role
        if ($user->role === 'etudiant') {
            $validator = Validator::make($request->all(), $this->studentProfileRules());
        } else if ($user->role === 'entreprise') {
            $validator = Validator::make($request->all(), [
                'nom_entreprise' => 'required|string|max:255',
                'secteur_activite' => 'required|string|max:255',
                'adresse' => 'required|string|max:500',
                'description' => 'required|string|max:1000',
                'ville' => 'nullable|string|max:255',
                'telephone' => 'nullable|string|max:20',
                'site_web' => 'nullable|url|max:500',
                'linkedin' => 'nullable|url|max:500',
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Rôle utilisateur invalide',
            ], 400);
        }

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update profile
        $profile->update($validator->validated());

        $profileData = $profile->fresh()->toArray();
        $profileData['photo_url'] = $profile->photo_path ? asset('storage/' . $profile->photo_path) : null;
        $profileData['experiences'] = $profile->experiences ?? [];
        $profileData['projects'] = $profile->projects ?? [];
        $profileData['languages'] = $profile->languages ?? [];
        $completion = $this->profileCompletionData($user, $profile);
        $profileData = array_merge($profileData, $completion);

        return response()->json(array_merge([
            'success' => true,
            'message' => 'Profil mis à jour avec succès',
            'profile' => $profileData,
        ], $completion), 200);
    }

    /**
     * Upload profile photo
     */
    public function uploadPhoto(Request $request)
    {
        $user = $request->user();
        $profile = $user->profile;

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun profil trouvé. Veuillez créer votre profil d\'abord.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Delete old photo if exists
        if ($profile->photo_path) {
            $oldPath = storage_path('app/public/' . $profile->photo_path);
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        // Store new photo
        $path = $request->file('photo')->store('profile-photos', 'public');
        $profile->photo_path = $path;
        $profile->save();
        $completion = $this->profileCompletionData($user, $profile);

        return response()->json(array_merge([
            'success' => true,
            'message' => 'Photo téléchargée avec succès',
            'photo_url' => asset('storage/' . $path),
        ], $completion), 200);
    }

    private function profileCompletionData($user, ?Profile $profile): array
    {
        if ($user->role !== 'etudiant') {
            return [];
        }

        return $this->profileCompletionService->calculate($user, $profile);
    }

    private function studentProfileRules(): array
    {
        return [
            'formation' => 'required|string|max:255',
            'specialite' => 'required|string|max:255',
            'niveau' => 'required|string|max:255',
            'telephone' => 'nullable|string|max:20',
            'adresse' => 'nullable|string|max:500',
            'ville' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
            'linkedin' => 'nullable|url|max:500',
            'github' => 'nullable|url|max:500',
            'experiences' => 'nullable|array|max:20',
            'experiences.*.poste' => 'required|string|max:255',
            'experiences.*.entreprise' => 'required|string|max:255',
            'experiences.*.lieu' => 'nullable|string|max:255',
            'experiences.*.date_debut' => 'required|date_format:Y-m',
            'experiences.*.date_fin' => 'nullable|date_format:Y-m',
            'experiences.*.description' => 'nullable|string|max:2000',
            'projects' => 'nullable|array|max:20',
            'projects.*.nom' => 'required|string|max:255',
            'projects.*.technologies' => 'nullable|string|max:255',
            'projects.*.lien' => 'nullable|url|max:500',
            'projects.*.description' => 'nullable|string|max:2000',
            'languages' => 'nullable|array|max:20',
            'languages.*.nom' => 'required|string|max:100',
            'languages.*.niveau' => 'required|in:Débutant,Intermédiaire,Avancé,Bilingue',
        ];
    }
}
