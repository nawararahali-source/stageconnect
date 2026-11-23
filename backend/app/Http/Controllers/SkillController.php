<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SkillController extends Controller
{
    /**
     * Get user's skills
     */
    public function index(Request $request)
    {
        $skills = $request->user()->skills;

        return response()->json([
            'success' => true,
            'skills' => $skills,
        ]);
    }

    /**
     * Create a new skill
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'level' => 'required|in:débutant,intermédiaire,avancé',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $skill = $request->user()->skills()->create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Compétence ajoutée avec succès',
            'skill' => $skill,
        ], 201);
    }

    /**
     * Update a skill
     */
    public function update(Request $request, $id)
    {
        $skill = $request->user()->skills()->find($id);

        if (!$skill) {
            return response()->json([
                'success' => false,
                'message' => 'Compétence non trouvée',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'level' => 'required|in:débutant,intermédiaire,avancé',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $skill->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Compétence mise à jour avec succès',
            'skill' => $skill,
        ]);
    }

    /**
     * Delete a skill
     */
    public function destroy(Request $request, $id)
    {
        $skill = $request->user()->skills()->find($id);

        if (!$skill) {
            return response()->json([
                'success' => false,
                'message' => 'Compétence non trouvée',
            ], 404);
        }

        $skill->delete();

        return response()->json([
            'success' => true,
            'message' => 'Compétence supprimée avec succès',
        ]);
    }
}
