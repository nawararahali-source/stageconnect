<?php

namespace App\Http\Controllers;

use App\Models\Cv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CvController extends Controller
{
    /**
     * Get user's CVs
     */
    public function index(Request $request)
    {
        $cvs = $request->user()->cvs;

        $cvsData = $cvs->map(function ($cv) {
            return [
                'id' => $cv->id,
                'file_name' => $cv->file_name,
                'file_size' => $cv->file_size,
                'mime_type' => $cv->mime_type,
                'download_url' => asset('storage/' . $cv->file_path),
                'created_at' => $cv->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'cvs' => $cvsData,
        ]);
    }

    /**
     * Upload a CV
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cv' => 'required|file|mimes:pdf|max:5120', // Max 5MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $file = $request->file('cv');
        
        // Delete old CVs if exists (keep only one CV per user)
        $request->user()->cvs()->each(function ($cv) {
            $oldPath = storage_path('app/public/' . $cv->file_path);
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
            $cv->delete();
        });

        // Store new CV
        $path = $file->store('cvs', 'public');

        $cv = $request->user()->cvs()->create([
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'CV téléchargé avec succès',
            'cv' => [
                'id' => $cv->id,
                'file_name' => $cv->file_name,
                'file_size' => $cv->file_size,
                'download_url' => asset('storage/' . $cv->file_path),
            ],
        ], 201);
    }

    /**
     * Delete a CV
     */
    public function destroy(Request $request, $id)
    {
        $cv = $request->user()->cvs()->find($id);

        if (!$cv) {
            return response()->json([
                'success' => false,
                'message' => 'CV non trouvé',
            ], 404);
        }

        // Delete file
        $filePath = storage_path('app/public/' . $cv->file_path);
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $cv->delete();

        return response()->json([
            'success' => true,
            'message' => 'CV supprimé avec succès',
        ]);
    }
}
