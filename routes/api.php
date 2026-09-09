<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Catálogo público de planes y especialidades
Route::get('/plans', [PlanController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/user', fn (Request $request) => $request->user());

    // ============================================================
    // SISTEMA DE PLANES / SUSCRIPCIONES (nuevo)
    // ============================================================
    Route::prefix('me')->group(function () {
        Route::get('/subscription',            [SubscriptionController::class, 'current']);
        Route::get('/subscriptions',           [SubscriptionController::class, 'history']);
        Route::get('/subscription-requests',   [SubscriptionController::class, 'myRequests']);
        Route::post('/subscription-requests',  [SubscriptionController::class, 'requestSubscription']);
        Route::post('/choose-free-specialty',  [SubscriptionController::class, 'chooseFreeSpecialty']);
    });

    // ============================================================
    // QUIZ / ESTUDIO (legacy, compat con front actual)
    // Protegido con specialty.access para validar plan activo.
    // ============================================================
    Route::get('/specialties', [QuizController::class, 'specialties']);
    Route::get('/specialties/{id}/topics', [QuizController::class, 'topics'])->whereNumber('id');
    Route::get('/questions/study', [QuizController::class, 'study'])
        ->middleware('specialty.access:questions');
    Route::post('/questions/{id}/answer', [QuizController::class, 'answer'])
        ->whereNumber('id');
    Route::get('/questions/errors', [QuizController::class, 'errors']);
    Route::get('/questions/bookmarked', [QuizController::class, 'bookmarked']);
    Route::post('/questions/{id}/bookmark', [QuizController::class, 'toggleBookmark'])->whereNumber('id');

    // Flashcards
    Route::get('/flashcards', [QuizController::class, 'flashcards'])
        ->middleware('specialty.access:flashcards');
    Route::post('/flashcards/{id}/progress', [QuizController::class, 'progressFlashcard'])
        ->whereNumber('id');

    // User
    Route::get('/user/quota', [QuizController::class, 'quota']);
    Route::post('/user/premium-toggle', [QuizController::class, 'togglePremium']);
    Route::get('/user/stats', [QuizController::class, 'stats']);

    // Admin (solo rol admin)
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users', [AdminController::class, 'storeUser']);
        Route::patch('/users/{id}', [AdminController::class, 'updateUser'])->whereNumber('id');
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser'])->whereNumber('id');
        Route::post('/users/{id}/restore', [AdminController::class, 'restoreUser'])->whereNumber('id');
        Route::get('/questions', [AdminController::class, 'questions']);
        Route::get('/questions/{id}', [AdminController::class, 'showQuestion'])->whereNumber('id');
        Route::patch('/questions/{id}', [AdminController::class, 'updateQuestion'])->whereNumber('id');
        Route::get('/specialties', [AdminController::class, 'specialties']);
        Route::post('/specialties', [AdminController::class, 'storeSpecialty']);
        Route::patch('/specialties/{id}', [AdminController::class, 'updateSpecialty'])->whereNumber('id');
        Route::delete('/specialties/{id}', [AdminController::class, 'deleteSpecialty'])->whereNumber('id');

        // Gestión manual de suscripciones por admin
        Route::post('/users/{user}/subscriptions', [SubscriptionController::class, 'adminActivate'])
            ->whereNumber('user');
        Route::post('/users/{user}/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'adminCancel'])
            ->whereNumber('user')->whereNumber('subscription');

        // Flujo de aprobación de solicitudes WhatsApp
        Route::get('/subscription-requests',  [SubscriptionController::class, 'adminListRequests']);
        Route::post('/subscription-requests/{id}/approve', [SubscriptionController::class, 'adminApprove'])
            ->whereNumber('id');
        Route::post('/subscription-requests/{id}/reject',  [SubscriptionController::class, 'adminReject'])
            ->whereNumber('id');
    });
});
