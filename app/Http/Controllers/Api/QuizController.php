<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Flashcard;
use App\Models\Question;
use App\Models\Specialty;
use App\Models\Topic;
use App\Models\UserAnswer;
use App\Models\UserBookmark;
use App\Models\UserFlashcardProgress;
use App\Services\QuotaService;
use App\Services\StreakService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizController extends Controller
{
    public function __construct(
        private QuotaService $quotaService,
        private StreakService $streakService,
        private SubscriptionService $subscriptionService,
    ) {}

    /**
     * GET /api/specialties
     * Lista las especialidades con conteo de preguntas, flashcards y progreso del usuario.
     */
    public function specialties(Request $request): JsonResponse
    {
        $user = $request->user();
        $isPremium = $this->quotaService->isPremium($user);
        $totalAnswered = $this->quotaService->getTotalAnsweredCount($user);

        $specialties = Specialty::where('is_active', true)
            ->with(['topics' => function ($q) {
                $q->withCount(['questions' => fn ($q2) => $q2->where('is_active', true)])
                    ->orderBy('sort_order')
                    ->orderBy('name');
            }])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $userId = $user->id;

        $payload = $specialties->map(function (Specialty $s) use ($userId, $isPremium, $totalAnswered) {
            $total = Question::where('specialty_id', $s->id)->where('is_active', true)->count();
            $flashcards = Flashcard::where('specialty_id', $s->id)->where('is_active', true)->count();

            $answered = UserAnswer::where('user_id', $userId)
                ->whereHas('question', fn ($q) => $q->where('specialty_id', $s->id))
                ->count();

            $correct = UserAnswer::where('user_id', $userId)
                ->where('is_correct', true)
                ->whereHas('question', fn ($q) => $q->where('specialty_id', $s->id))
                ->count();

            $freeLimit = QuotaService::FREE_LIMIT_PER_SPECIALTY;
            $isLocked = ! $isPremium && ($answered >= $freeLimit || $totalAnswered >= QuotaService::FREE_TOTAL_LIMIT);

            $topics = $s->topics
                ->filter(fn ($t) => $t->questions_count > 0)
                ->values()
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'code' => $t->code,
                    'name' => $this->formatTopicName($t->name),
                    'slug' => $t->slug,
                    'questions_count' => $t->questions_count,
                ]);

            return [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->name,
                'slug' => $s->slug,
                'icon' => $s->icon,
                'color' => $s->color,
                'is_locked' => $isLocked,
                'free_limit' => $freeLimit,
                'free_remaining' => $isPremium ? null : max(0, $freeLimit - $answered),
                'topics' => $topics,
                'stats' => [
                    'total_questions' => $total,
                    'total_flashcards' => $flashcards,
                    'answered' => $answered,
                    'correct' => $correct,
                    'accuracy' => $answered > 0 ? round(($correct / $answered) * 100, 1) : 0,
                    'progress' => $total > 0 ? round(min(100, ($answered / $total) * 100), 1) : 0,
                ],
            ];
        });

        return response()->json([
            'specialties' => $payload,
            'streak' => $this->streakService->getStreakStats($user),
            'quota' => $this->quotaService->getQuotaStatus($user),
        ]);
    }

    /**
     * GET /api/specialties/{id}/topics
     */
    public function topics(int $id): JsonResponse
    {
        $specialty = Specialty::findOrFail($id);
        $topics = Topic::where('specialty_id', $specialty->id)
            ->withCount(['questions' => fn ($q) => $q->where('is_active', true)])
            ->having('questions_count', '>', 0)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'code' => $t->code,
                'name' => $this->formatTopicName($t->name),
                'slug' => $t->slug,
                'questions_count' => $t->questions_count,
            ]);

        return response()->json([
            'specialty' => [
                'id' => $specialty->id,
                'name' => $specialty->name,
                'code' => $specialty->code,
            ],
            'topics' => $topics,
        ]);
    }

    /**
     * GET /api/questions/study
     * Devuelve un lote de preguntas para estudiar.
     * Query params: specialty_id, topic_id, mode (free|review), limit
     */
    public function study(Request $request): JsonResponse
    {
        $user = $request->user();
        $isPremium = $this->quotaService->isPremium($user);

        $request->validate([
            'specialty_id' => ['nullable', 'integer', 'exists:specialties,id'],
            'topic_id' => ['nullable', 'integer', 'exists:topics,id'],
            'mode' => ['nullable', 'in:free,review'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $specialtyId = $request->input('specialty_id');

        if (! $isPremium) {
            if ($this->quotaService->getTotalAnsweredCount($user) >= QuotaService::FREE_TOTAL_LIMIT) {
                return response()->json([
                    'message' => 'Has completado el límite de 12 preguntas del plan gratuito. Pasate a Premium para acceder a todas las preguntas del banco.',
                    'requires_premium' => true,
                    'is_locked' => true,
                    'quota' => $this->quotaService->getQuotaStatus($user),
                ], 403);
            }

            if ($specialtyId && ! $this->quotaService->canAnswerSpecialty($user, (int) $specialtyId)) {
                return response()->json([
                    'message' => 'Has completado el límite gratuito de 4 preguntas para esta especialidad. Pasate a Premium para continuar estudiando.',
                    'requires_premium' => true,
                    'is_locked' => true,
                    'quota' => $this->quotaService->getQuotaStatus($user),
                ], 403);
            }
        }

        $limit = (int) $request->input('limit', 10);
        if (! $isPremium && $specialtyId) {
            $answeredInSpec = $this->quotaService->getAnsweredCountForSpecialty($user, (int) $specialtyId);
            $remainingInSpec = max(0, QuotaService::FREE_LIMIT_PER_SPECIALTY - $answeredInSpec);
            $limit = min($limit, $remainingInSpec);
        }

        $mode = $request->input('mode', 'free');

        $query = Question::query()->where('is_active', true)
            ->with(['specialty:id,name,code', 'topic:id,name,code', 'section:id,name,code']);

        if ($specialtyId) {
            $query->where('specialty_id', $specialtyId);
        } else {
            // Sin specialty_id: filtrar por las specialties a las que el user tiene acceso
            $active = $user->activeSubscription();
            if (! $user->hasRole('admin') && $active && ! $active->plan->isUnlimited()) {
                $query->whereIn('specialty_id', $active->specialties->pluck('id'));
            }
        }
        if ($topicId = $request->input('topic_id')) {
            $query->where('topic_id', $topicId);
        }

        // Prioriza preguntas que el usuario NO ha respondido todavía.
        $answeredIds = UserAnswer::where('user_id', $user->id)->pluck('question_id');
        $query->whereNotIn('id', $answeredIds);

        if ($mode === 'review') {
            // Modo repaso: limita a temas que ya tuvo al menos 1 respuesta correcta.
            $query->whereIn('id', function ($q) use ($user) {
                $q->select('question_id')
                    ->from('user_answers')
                    ->where('user_id', $user->id)
                    ->where('is_correct', true);
            });
        }

        $questions = $query->inRandomOrder()->limit($limit)->get()->map(fn (Question $q) => $this->serialize($q));

        return response()->json([
            'questions' => $questions,
            'quota' => $this->quotaService->getQuotaStatus($user),
        ]);
    }

    /**
     * POST /api/questions/{id}/answer
     */
    public function answer(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $isPremium = $this->quotaService->isPremium($user);

        $data = $request->validate([
            'selected_answer' => ['required', 'string', 'in:a,b,c,d,e,f,g'],
            'time_spent_seconds' => ['nullable', 'integer', 'min:0'],
            'mode' => ['nullable', 'in:free,errors,review'],
        ]);

        $question = Question::findOrFail($id);

        // Nuevo: validar acceso por plan (Básico/Estudiante/Full por especialidad)
        $access = $this->subscriptionService->checkSpecialtyAccess($user, $question->specialty_id);
        if (! $access['allowed']) {
            return response()->json([
                'message'   => 'No tenés acceso a esta materia con tu plan actual.',
                'reason'    => $access['reason'],
                'specialty' => [
                    'id'   => $question->specialty_id,
                    'name' => $question->specialty?->name,
                ],
                'plans'     => \App\Models\Plan::where('is_active', true)
                    ->orderBy('sort_order')
                    ->get(['id', 'name', 'slug', 'price', 'currency', 'duration_days', 'max_specialties', 'includes_flashcards']),
            ], 402);
        }

        // Si ya respondió esta pregunta, no consumimos cuota de nuevo (idempotencia).
        $already = UserAnswer::where('user_id', $user->id)
            ->where('question_id', $question->id)
            ->exists();

        if (! $isPremium && ! $already) {
            if ($this->quotaService->getTotalAnsweredCount($user) >= QuotaService::FREE_TOTAL_LIMIT) {
                return response()->json([
                    'message' => 'Has alcanzado el límite de 12 preguntas del plan gratuito.',
                    'requires_premium' => true,
                    'is_locked' => true,
                    'quota' => $this->quotaService->getQuotaStatus($user),
                ], 403);
            }

            if (! $this->quotaService->canAnswerSpecialty($user, $question->specialty_id)) {
                return response()->json([
                    'message' => 'Has alcanzado el límite de 4 preguntas de prueba para esta especialidad.',
                    'requires_premium' => true,
                    'is_locked' => true,
                    'quota' => $this->quotaService->getQuotaStatus($user),
                ], 403);
            }
        }

        $isCorrect = strtolower($data['selected_answer']) === strtolower($question->correct_answer);

        $answer = UserAnswer::create([
            'user_id' => $user->id,
            'question_id' => $question->id,
            'selected_answer' => strtolower($data['selected_answer']),
            'is_correct' => $isCorrect,
            'time_spent_seconds' => $data['time_spent_seconds'] ?? 0,
            'mode' => $data['mode'] ?? 'free',
        ]);

        $streak = $this->streakService->recordAnswer($user, $isCorrect);

        return response()->json([
            'is_correct' => $isCorrect,
            'correct_answer' => $question->correct_answer,
            'correct_justification' => $question->correct_justification,
            'incorrect_justification' => $question->incorrect_justification,
            'source_justification' => $question->source_justification,
            'source_file' => $question->source_file,
            'catedra' => $question->catedra,
            'streak' => $streak,
            'quota' => $this->quotaService->getQuotaStatus($user),
        ]);
    }

    /**
     * GET /api/questions/errors
     * Banco de errores: preguntas que el usuario falló y que aún no re-acertó.
     */
    public function errors(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->quotaService->isFeatureAllowed($user, 'errors')) {
            return response()->json([
                'message' => 'El Banco de Fallos es exclusivo para miembros Premium.',
                'requires_premium' => true,
            ], 403);
        }

        $request->validate([
            'specialty_id' => ['nullable', 'integer', 'exists:specialties,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $limit = (int) $request->input('limit', 20);

        // IDs de preguntas que el usuario respondió mal al menos una vez
        // y NO las ha acertado en un reintento posterior.
        $failedIds = DB::table('user_answers as ua1')
            ->where('ua1.user_id', $user->id)
            ->where('ua1.is_correct', false)
            ->whereNotExists(function ($q) use ($user) {
                $q->select(DB::raw(1))
                    ->from('user_answers as ua2')
                    ->whereColumn('ua2.question_id', 'ua1.question_id')
                    ->where('ua2.user_id', $user->id)
                    ->where('ua2.is_correct', true)
                    ->whereColumn('ua2.created_at', '>', 'ua1.created_at');
            })
            ->pluck('ua1.question_id')
            ->unique()
            ->values();

        $query = Question::whereIn('id', $failedIds)
            ->where('is_active', true)
            ->with(['specialty:id,name,code', 'topic:id,name,code', 'section:id,name,code']);

        if ($specialtyId = $request->input('specialty_id')) {
            $query->where('specialty_id', $specialtyId);
        }

        $questions = $query->inRandomOrder()->limit($limit)->get()
            ->map(fn (Question $q) => $this->serialize($q, withErrorCount: true, userId: $user->id));

        return response()->json([
            'questions' => $questions,
            'total_errors' => $failedIds->count(),
        ]);
    }

    /**
     * GET /api/flashcards
     */
    public function flashcards(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->quotaService->isFeatureAllowed($user, 'flashcards')) {
            return response()->json([
                'message' => 'Las Flashcards clínicas son exclusivas para miembros con plan pago.',
                'requires_premium' => true,
            ], 403);
        }

        $request->validate([
            'specialty_id' => ['nullable', 'integer', 'exists:specialties,id'],
            'topic_id' => ['nullable', 'integer', 'exists:topics,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = (int) $request->input('limit', 30);

        $query = Flashcard::where('is_active', true)
            ->with(['specialty:id,name,code', 'topic:id,name,code', 'section:id,name,code']);

        // Filtro por specialty_id explícito
        if ($specialtyId = $request->input('specialty_id')) {
            $query->where('specialty_id', $specialtyId);
        } else {
            // Sin specialty_id: filtrar por las specialties a las que el user tiene acceso
            $active = $user->activeSubscription();
            if (! $user->hasRole('admin') && $active && ! $active->plan->isUnlimited()) {
                $query->whereIn('specialty_id', $active->specialties->pluck('id'));
            }
        }

        if ($topicId = $request->input('topic_id')) {
            $query->where('topic_id', $topicId);
        }

        $userId = $request->user()->id;
        $cards = $query->inRandomOrder()->limit($limit)->get()
            ->map(function (Flashcard $c) use ($userId) {
                $prog = UserFlashcardProgress::where('user_id', $userId)
                    ->where('flashcard_id', $c->id)
                    ->first();

                return [
                    'id' => $c->id,
                    'front' => $c->front,
                    'back' => $c->back,
                    'justification' => $c->justification,
                    'specialty' => $c->specialty?->name,
                    'topic' => $c->topic?->name,
                    'section' => $c->section?->name,
                    'catedra' => $c->catedra,
                    'knew_it' => $prog?->knew_it,
                    'review_count' => $prog?->review_count ?? 0,
                ];
            });

        return response()->json(['flashcards' => $cards]);
    }

    /**
     * POST /api/flashcards/{id}/progress
     * Guarda si el usuario sabía la flashcard o desea repasarla.
     */
    public function progressFlashcard(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (! $this->quotaService->isFeatureAllowed($user, 'flashcards')) {
            return response()->json([
                'message' => 'Las Flashcards clínicas son exclusivas para miembros Premium.',
                'requires_premium' => true,
            ], 403);
        }

        $request->validate([
            'knew_it' => ['required', 'boolean'],
        ]);

        $card = Flashcard::findOrFail($id);
        $user = $request->user();

        $progress = UserFlashcardProgress::firstOrNew([
            'user_id' => $user->id,
            'flashcard_id' => $card->id,
        ]);

        $progress->knew_it = $request->boolean('knew_it');
        $progress->review_count = ($progress->review_count ?? 0) + 1;
        $progress->last_seen_at = now();
        $progress->save();

        return response()->json([
            'success' => true,
            'progress' => [
                'flashcard_id' => $card->id,
                'knew_it' => $progress->knew_it,
                'review_count' => $progress->review_count,
                'last_seen_at' => $progress->last_seen_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/user/quota
     */
    public function quota(Request $request): JsonResponse
    {
        return response()->json([
            'quota' => $this->quotaService->getQuotaStatus($request->user()),
            'streak' => $this->streakService->getStreakStats($request->user()),
        ]);
    }

    /**
     * POST /api/questions/{id}/bookmark
     * Toggle: si existe, lo elimina; si no, lo crea.
     */
    public function toggleBookmark(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $question = Question::findOrFail($id);

        $existing = UserBookmark::where('user_id', $user->id)
            ->where('question_id', $question->id)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['bookmarked' => false, 'message' => 'Eliminada de guardados']);
        }

        UserBookmark::create([
            'user_id' => $user->id,
            'question_id' => $question->id,
        ]);

        return response()->json(['bookmarked' => true, 'message' => 'Guardada']);
    }

    /**
     * GET /api/questions/bookmarked
     */
    public function bookmarked(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->validate([
            'specialty_id' => ['nullable', 'integer', 'exists:specialties,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $limit = (int) $request->input('limit', 50);

        $query = Question::whereIn('id', function ($q) use ($user) {
            $q->select('question_id')
                ->from('user_bookmarks')
                ->where('user_id', $user->id);
        })->where('is_active', true)
            ->with(['specialty:id,name,code', 'topic:id,name,code', 'section:id,name,code']);

        if ($specialtyId = $request->input('specialty_id')) {
            $query->where('specialty_id', $specialtyId);
        }

        $questions = $query->inRandomOrder()->limit($limit)->get()
            ->map(fn (Question $q) => $this->serialize($q));

        return response()->json([
            'questions' => $questions,
            'total' => $questions->count(),
        ]);
    }

    /**
     * GET /api/user/stats
     * Métricas globales del alumno para el dashboard.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;

        $totalAnswered = UserAnswer::where('user_id', $userId)->count();
        $totalCorrect = UserAnswer::where('user_id', $userId)->where('is_correct', true)->count();
        $totalQuestions = Question::count();
        $totalFlashcards = Flashcard::count();
        $totalBookmarks = UserBookmark::where('user_id', $userId)->count();
        $totalFlashcardsLearned = UserFlashcardProgress::where('user_id', $userId)
            ->where('knew_it', true)
            ->count();

        $bySpecialty = Specialty::orderBy('name')->get()->map(function (Specialty $s) use ($userId) {
            $total = Question::where('specialty_id', $s->id)->where('is_active', true)->count();
            $answered = UserAnswer::where('user_id', $userId)
                ->whereHas('question', fn ($q) => $q->where('specialty_id', $s->id))
                ->count();
            $correct = UserAnswer::where('user_id', $userId)
                ->where('is_correct', true)
                ->whereHas('question', fn ($q) => $q->where('specialty_id', $s->id))
                ->count();
            return [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->code,
                'total' => $total,
                'answered' => $answered,
                'correct' => $correct,
                'accuracy' => $answered > 0 ? round(($correct / $answered) * 100, 1) : 0,
                'progress' => $total > 0 ? round(min(100, ($answered / $total) * 100), 1) : 0,
            ];
        });

        return response()->json([
            'global' => [
                'total_questions' => $totalQuestions,
                'total_answered' => $totalAnswered,
                'total_correct' => $totalCorrect,
                'accuracy' => $totalAnswered > 0 ? round(($totalCorrect / $totalAnswered) * 100, 1) : 0,
                'progress' => $totalQuestions > 0 ? round(min(100, ($totalAnswered / $totalQuestions) * 100), 1) : 0,
                'total_flashcards' => $totalFlashcards,
                'flashcards_learned' => $totalFlashcardsLearned,
                'bookmarks' => $totalBookmarks,
            ],
            'by_specialty' => $bySpecialty,
            'streak' => $this->streakService->getStreakStats($user),
            'quota' => $this->quotaService->getQuotaStatus($user),
        ]);
    }

    /**
     * POST /api/user/premium-toggle
     * Endpoint de testing / admin: alterna is_premium.
     */
    public function togglePremium(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->is_premium = ! $user->is_premium;
        if ($user->is_premium) {
            $user->premium_until = now()->addYear();
        } else {
            $user->premium_until = null;
            $this->quotaService->resetQuota($user);
        }
        $user->save();

        return response()->json([
            'is_premium' => $user->is_premium,
            'premium_until' => $user->premium_until?->toIso8601String(),
            'quota' => $this->quotaService->getQuotaStatus($user),
        ]);
    }

    private function serialize(Question $q, bool $withErrorCount = false, ?int $userId = null): array
    {
        $data = [
            'id' => $q->id,
            'code_number' => $q->code_number,
            'statement' => $q->statement,
            'options' => $q->options,
            'correct_answer' => $q->correct_answer,
            'catedra' => $q->catedra,
            'subtopic' => $q->subtopic,
            'source_file' => $q->source_file,
            'specialty' => $q->specialty?->only(['id', 'name', 'code']),
            'topic' => $q->topic ? [
                'id' => $q->topic->id,
                'code' => $q->topic->code,
                'name' => $this->formatTopicName($q->topic->name),
            ] : null,
            'section' => $q->section?->only(['id', 'name', 'code']),
        ];

        if ($userId) {
            $data['is_bookmarked'] = UserBookmark::where('user_id', $userId)
                ->where('question_id', $q->id)
                ->exists();
        }

        if ($withErrorCount && $userId) {
            $data['error_count'] = UserAnswer::where('user_id', $userId)
                ->where('question_id', $q->id)
                ->where('is_correct', false)
                ->count();
        }

        return $data;
    }

    private function formatTopicName(string $name): string
    {
        // Replace underscores with spaces
        $name = str_replace('_', ' ', $name);
        // Normalize multiple spaces
        $name = preg_replace('/\s+/', ' ', trim($name));

        // Dictionary of clinical medical acronyms
        $acronyms = [
            'tep' => 'TEP',
            'epoc' => 'EPOC',
            'hta' => 'HTA',
            'itu' => 'ITU',
            'ira' => 'IRA',
            'irc' => 'IRC',
            'sdra' => 'SDRA',
            'tbc' => 'TBC',
            'cv' => 'CV',
            'vd' => 'VD',
            'vi' => 'VI',
        ];

        $words = explode(' ', $name);
        foreach ($words as &$w) {
            $clean = mb_strtolower(trim($w, ".,:;()"), 'UTF-8');
            if (isset($acronyms[$clean])) {
                $w = str_ireplace($clean, $acronyms[$clean], $w);
            }
        }

        return implode(' ', $words);
    }
}
