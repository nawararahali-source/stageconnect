<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileAnalysisController;
use App\Http\Controllers\SkillController;
use App\Http\Controllers\CvController;
use App\Http\Controllers\InternshipOfferController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\MatchingController;
use App\Http\Controllers\DashboardStatsController;
use App\Http\Controllers\CoverLetterController;
use App\Http\Controllers\ConversationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Routes publiques d'authentification
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Sonde de sante (publique) : utilisee par Docker, le CI et la supervision
Route::get('/health', function () {
    try {
        \Illuminate\Support\Facades\DB::select('select 1');
        $db = 'up';
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Health check: base de donnees indisponible', [
            'message' => $e->getMessage(),
        ]);
        $db = 'down';
    }

    return response()->json([
        'status' => $db === 'up' ? 'ok' : 'degraded',
        'database' => $db,
        'version' => env('APP_VERSION', 'dev'),
        'time' => now()->toIso8601String(),
    ], $db === 'up' ? 200 : 503);
});

// Routes protégées
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Application conversations and messages
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages']);
    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'storeMessage']);
    Route::post('/applications/{application}/conversation', [ConversationController::class, 'startForApplication']);
    Route::get('/messages/unread-count', [ConversationController::class, 'unreadCount']);
    
    // Profile routes
    Route::post('/profile', [ProfileController::class, 'store']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/photo', [ProfileController::class, 'uploadPhoto']);
    
    // Skills routes
    Route::get('/skills', [SkillController::class, 'index']);
    Route::post('/skills', [SkillController::class, 'store']);
    Route::put('/skills/{id}', [SkillController::class, 'update']);
    Route::delete('/skills/{id}', [SkillController::class, 'destroy']);
    
    // CV routes
    Route::get('/cv', [CvController::class, 'index']);
    Route::post('/cv', [CvController::class, 'store']);
    Route::delete('/cv/{id}', [CvController::class, 'destroy']);

    // Profile Analysis routes (students only)
    Route::post('/profile/analyze', [ProfileAnalysisController::class, 'analyze']);

    // Company internship offer routes
    Route::get('/company/offers', [InternshipOfferController::class, 'index']);
    Route::post('/company/offers', [InternshipOfferController::class, 'store']);
    Route::put('/company/offers/{internshipOffer}', [InternshipOfferController::class, 'update']);
    Route::delete('/company/offers/{internshipOffer}', [InternshipOfferController::class, 'destroy']);

    // Student internship offer routes
    Route::get('/student/offers', [ApplicationController::class, 'indexOffers']);
    Route::post('/student/offers/{offer}/apply', [ApplicationController::class, 'apply']);
    Route::get('/student/applications', [ApplicationController::class, 'indexStudentApplications']);
    Route::post('/offers/{offer}/cover-letter', [CoverLetterController::class, 'generate']);

    // Company application management routes
    Route::get('/company/applications', [ApplicationController::class, 'indexCompanyApplications']);
    Route::patch('/company/applications/{application}/status', [ApplicationController::class, 'updateStatus']);

    // Company matching routes
    Route::get('/company/offers/{offer}/matching', [MatchingController::class, 'getOfferMatching']);

    // Dashboard statistics routes
    Route::get('/company/stats', [DashboardStatsController::class, 'company']);
    Route::get('/student/stats', [DashboardStatsController::class, 'student']);
});