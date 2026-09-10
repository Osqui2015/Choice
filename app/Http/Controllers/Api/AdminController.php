<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Flashcard;
use App\Models\Question;
use App\Models\Specialty;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    // ============================================================
    //  STATS
    // ============================================================

    public function stats(): JsonResponse
    {
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $inactiveUsers = User::where('is_active', false)->count();
        $premiumUsers = User::where('is_premium', true)->count();

        $totalQuestions = Question::count();
        $activeQuestions = Question::where('is_active', true)->count();
        $totalFlashcards = Flashcard::count();
        $activeFlashcards = Flashcard::where('is_active', true)->count();
        $totalSpecialties = Specialty::count();
        $activeSpecialties = Specialty::where('is_active', true)->count();

        $totalAnswers = UserAnswer::count();
        $totalCorrect = UserAnswer::where('is_correct', true)->count();
        $globalAccuracy = $totalAnswers > 0 ? round(($totalCorrect / $totalAnswers) * 100, 1) : 0;

        $answersToday = UserAnswer::whereDate('created_at', today())->count();
        $activeUsersToday = UserAnswer::whereDate('created_at', today())
            ->distinct('user_id')
            ->count('user_id');

        // Top 5 usuarios con más respuestas correctas
        $topUsers = User::withCount(['answers as correct_count' => fn ($q) => $q->where('is_correct', true)])
            ->where('is_active', true)
            ->orderByDesc('correct_count')
            ->limit(5)
            ->get(['id', 'name', 'email'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'correct_count' => $u->correct_count,
            ]);

        // Distribución por especialidad
        $bySpecialty = Specialty::orderBy('name')->get()->map(function (Specialty $s) {
            return [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->code,
                'is_active' => $s->is_active,
                'total_questions' => Question::where('specialty_id', $s->id)->count(),
                'active_questions' => Question::where('specialty_id', $s->id)->where('is_active', true)->count(),
            ];
        });

        return response()->json([
            'users' => [
                'total' => $totalUsers,
                'active' => $activeUsers,
                'inactive' => $inactiveUsers,
                'premium' => $premiumUsers,
            ],
            'content' => [
                'specialties_total' => $totalSpecialties,
                'specialties_active' => $activeSpecialties,
                'questions_total' => $totalQuestions,
                'questions_active' => $activeQuestions,
                'flashcards_total' => $totalFlashcards,
                'flashcards_active' => $activeFlashcards,
            ],
            'activity' => [
                'answers_total' => $totalAnswers,
                'answers_today' => $answersToday,
                'correct_total' => $totalCorrect,
                'global_accuracy' => $globalAccuracy,
                'active_users_today' => $activeUsersToday,
            ],
            'top_users' => $topUsers,
            'by_specialty' => $bySpecialty,
        ]);
    }

    // ============================================================
    //  USERS
    // ============================================================

    public function users(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'in:admin,usuario'],
            'is_premium' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $q = User::query()->with(['roles:id,name', 'activeSubscription.plan', 'activeSubscription.specialties:id,name,code', 'latestSubscription.plan']);

        if ($s = $request->input('search')) {
            $q->where(function ($w) use ($s) {
                $w->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%");
            });
        }
        if ($role = $request->input('role')) {
            $q->whereHas('roles', fn ($w) => $w->where('name', $role));
        }
        if ($request->has('is_premium')) {
            $q->where('is_premium', $request->boolean('is_premium'));
        }
        if ($request->has('is_active')) {
            $q->where('is_active', $request->boolean('is_active'));
        }

        $paginator = $q->orderByDesc('id')->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (User $u) => $this->serializeUser($u)),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function storeUser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'phone'    => ['nullable', 'string', 'min:8', 'max:32', 'regex:/^[+0-9\s\-()]+$/'],
            'password' => ['required', 'string', 'min:8'],
            'role'     => ['required', 'in:admin,usuario'],
            'is_premium' => ['nullable', 'boolean'],
            'is_active'  => ['nullable', 'boolean'],
            'premium_until' => ['nullable', 'date'],
            'accepts_promotions' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'name'                => $data['name'],
            'email'               => $data['email'],
            'phone'               => isset($data['phone']) ? preg_replace('/[^0-9]/', '', $data['phone']) : null,
            'password'            => $data['password'],
            'is_premium'          => $data['is_premium'] ?? false,
            'is_active'           => $data['is_active'] ?? true,
            'premium_until'       => $data['premium_until'] ?? null,
            'accepts_promotions'  => $data['accepts_promotions'] ?? false,
        ]);
        $user->assignRole($data['role']);

        \App\Models\UserDailyQuota::firstOrCreate(
            ['user_id' => $user->id],
            ['daily_limit' => 10, 'questions_answered_today' => 0]
        );

        return response()->json([
            'message' => 'Usuario creado',
            'user' => $this->serializeUser($user->fresh('roles')),
        ], 201);
    }

    public function updateUser(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $data = $request->validate([
            'name'     => ['nullable', 'string', 'max:255'],
            'email'    => ['nullable', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'    => ['nullable', 'string', 'min:8', 'max:32', 'regex:/^[+0-9\s\-()]+$/'],
            'password' => ['nullable', 'string', 'min:8'],
            'role'     => ['nullable', 'in:admin,usuario'],
            'is_premium' => ['nullable', 'boolean'],
            'is_active'  => ['nullable', 'boolean'],
            'premium_until' => ['nullable', 'date'],
            'accepts_promotions' => ['nullable', 'boolean'],
        ]);

        $isCurrentlyAdmin = $user->hasRole('admin');

        // Bloquear acciones que dejarian al sistema sin admins activos
        $willDeactivate = array_key_exists('is_active', $data) && ! (bool) $data['is_active'];
        $willDemote     = array_key_exists('role', $data) && $data['role'] !== 'admin' && $isCurrentlyAdmin;

        if (($willDeactivate || $willDemote) && $isCurrentlyAdmin && $this->countActiveAdmins() <= 1) {
            return response()->json([
                'message' => 'No podés desactivar ni cambiar el rol del último admin activo. Promové a otro usuario a admin primero.',
            ], 422);
        }

        $isSelf = $request->user()?->id === $user->id;

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        if (array_key_exists('name', $data)) $user->name = $data['name'];
        if (array_key_exists('email', $data)) $user->email = $data['email'];
        if (array_key_exists('phone', $data)) {
            $user->phone = $data['phone'] !== null ? preg_replace('/[^0-9]/', '', $data['phone']) : null;
        }
        if (array_key_exists('is_premium', $data)) $user->is_premium = (bool) $data['is_premium'];
        if (array_key_exists('is_active', $data)) {
            $user->is_active = (bool) $data['is_active'];
            if (! $user->is_active) {
                // Al desactivar, revocamos todos los tokens de Sanctum
                $user->tokens()->delete();
            }
        }
        if (array_key_exists('premium_until', $data)) $user->premium_until = $data['premium_until'];
        if (array_key_exists('accepts_promotions', $data)) $user->accepts_promotions = (bool) $data['accepts_promotions'];
        $user->save();

        if (! empty($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        return response()->json([
            'message' => 'Usuario actualizado',
            'user' => $this->serializeUser($user->fresh('roles')),
            'self_affected' => $isSelf && ($willDeactivate || ($data['role'] ?? null) === 'usuario'),
        ]);
    }

    public function deleteUser(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->hasRole('admin') && $this->countActiveAdmins() <= 1) {
            return response()->json([
                'message' => 'No podés deshabilitar al último admin activo. Promové a otro usuario a admin primero.',
            ], 422);
        }

        $isSelf = $request->user()?->id === $user->id;
        $user->is_active = false;
        $user->tokens()->delete(); // mata todas las sesiones
        $user->save();

        return response()->json([
            'message' => 'Usuario deshabilitado',
            'self_affected' => $isSelf,
        ]);
    }

    /**
     * Hard delete: elimina al usuario y todos sus datos asociados en
     * cascada (las FKs ya tienen cascadeOnDelete en las migraciones).
     * No se puede usar sobre el último admin activo.
     */
    public function forceDeleteUser(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->hasRole('admin') && $this->countActiveAdmins() <= 1) {
            return response()->json([
                'message' => 'No podés eliminar al último admin activo. Promové a otro usuario a admin primero.',
            ], 422);
        }

        $isSelf = $request->user()?->id === $user->id;
        $user->tokens()->delete();
        $user->delete(); // hard delete, cascade en FKs

        return response()->json([
            'message' => 'Usuario eliminado definitivamente',
            'self_affected' => $isSelf,
        ]);
    }

    public function restoreUser(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->is_active = true;
        $user->save();

        return response()->json(['message' => 'Usuario reactivado']);
    }

    // ============================================================
    //  QUESTIONS
    // ============================================================

    public function questions(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'specialty_id' => ['nullable', 'integer', 'exists:specialties,id'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $q = Question::with(['specialty:id,name,code', 'topic:id,name,code']);

        if ($s = $request->input('search')) {
            $q->where('statement', 'like', "%{$s}%");
        }
        if ($sid = $request->input('specialty_id')) {
            $q->where('specialty_id', $sid);
        }
        if ($request->has('is_active')) {
            $q->where('is_active', $request->boolean('is_active'));
        }

        $paginator = $q->orderByDesc('id')->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Question $q) => [
                'id' => $q->id,
                'code_number' => $q->code_number,
                'statement' => Str::limit($q->statement, 140),
                'correct_answer' => $q->correct_answer,
                'specialty' => $q->specialty?->only(['id', 'name', 'code']),
                'topic' => $q->topic?->only(['id', 'name']),
                'is_active' => (bool) $q->is_active,
                'updated_at' => $q->updated_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function updateQuestion(Request $request, int $id): JsonResponse
    {
        $question = Question::findOrFail($id);
        $data = $request->validate([
            'statement' => ['nullable', 'string'],
            'options' => ['nullable', 'array'],
            'correct_answer' => ['nullable', 'in:a,b,c,d,e'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('statement', $data)) $question->statement = $data['statement'];
        if (array_key_exists('options', $data)) $question->options = $data['options'];
        if (array_key_exists('correct_answer', $data)) $question->correct_answer = strtolower($data['correct_answer']);
        if (array_key_exists('is_active', $data)) $question->is_active = (bool) $data['is_active'];
        $question->save();

        return response()->json([
            'message' => 'Pregunta actualizada',
            'question' => $this->serializeQuestionFull($question),
        ]);
    }

    public function showQuestion(int $id): JsonResponse
    {
        $q = Question::with(['specialty:id,name,code', 'topic:id,name', 'section:id,name'])->findOrFail($id);
        return response()->json(['question' => $this->serializeQuestionFull($q)]);
    }

    // ============================================================
    //  SPECIALTIES
    // ============================================================

    public function specialties(): JsonResponse
    {
        $specialties = Specialty::orderBy('sort_order')->orderBy('name')
            ->withCount(['questions', 'flashcards'])
            ->get()
            ->map(fn (Specialty $s) => [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->name,
                'slug' => $s->slug,
                'icon' => $s->icon,
                'color' => $s->color,
                'sort_order' => $s->sort_order,
                'is_active' => (bool) $s->is_active,
                'questions_count' => $s->questions_count,
                'flashcards_count' => $s->flashcards_count,
            ]);

        return response()->json(['data' => $specialties]);
    }

    public function storeSpecialty(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'unique:specialties,code'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:specialties,slug'],
            'icon' => ['nullable', 'string', 'max:64'],
            'color' => ['nullable', 'string', 'max:16'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['is_active'] = $data['is_active'] ?? true;
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $s = Specialty::create($data);

        return response()->json(['message' => 'Especialidad creada', 'specialty' => $s], 201);
    }

    public function updateSpecialty(Request $request, int $id): JsonResponse
    {
        $s = Specialty::findOrFail($id);
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:64', Rule::unique('specialties', 'code')->ignore($s->id)],
            'name' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('specialties', 'slug')->ignore($s->id)],
            'icon' => ['nullable', 'string', 'max:64'],
            'color' => ['nullable', 'string', 'max:16'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $s->fill($data);
        if (! empty($data['name']) && empty($data['slug'])) {
            $s->slug = Str::slug($data['name']);
        }
        $s->save();

        return response()->json(['message' => 'Especialidad actualizada', 'specialty' => $s]);
    }

    public function deleteSpecialty(int $id): JsonResponse
    {
        $s = Specialty::findOrFail($id);
        $hasQuestions = Question::where('specialty_id', $s->id)->exists();
        if ($hasQuestions) {
            return response()->json([
                'message' => 'No se puede eliminar: la especialidad tiene preguntas asociadas. Desactivala en su lugar.',
            ], 422);
        }
        $s->delete();
        return response()->json(['message' => 'Especialidad eliminada']);
    }

    // ============================================================
    //  HELPERS
    // ============================================================

    /** Cuenta admins con is_active = true. */
    private function countActiveAdmins(): int
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
            ->count();
    }

    private function serializeUser(User $u): array
    {
        $active = $u->activeSubscription;
        $latest = $u->latestSubscription;

        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'accepts_promotions' => (bool) $u->accepts_promotions,
            'roles' => $u->roles->pluck('name'),
            'is_premium' => (bool) $u->is_premium,
            'is_active' => (bool) $u->is_active,
            'premium_until' => $u->premium_until?->toIso8601String(),
            'email_verified_at' => $u->email_verified_at?->toIso8601String(),
            'created_at' => $u->created_at?->toIso8601String(),
            'active_subscription' => $active ? $this->serializeSubscription($active) : null,
            'latest_subscription' => $latest && (! $active || $latest->id !== $active->id)
                ? $this->serializeSubscription($latest)
                : null,
        ];
    }

    private function serializeSubscription(UserSubscription $s): array
    {
        $now = now();
        $expires = $s->expires_at;
        $started = $s->started_at;
        $daysRemaining = $expires ? max(0, (int) $now->diffInDays($expires, false)) : 0;
        $daysTotal = ($started && $expires) ? max(1, (int) $started->diffInDays($expires)) : null;
        $isUnlimited = $s->plan?->isUnlimited() ?? false;

        return [
            'id' => $s->id,
            'status' => $s->status,
            'plan' => $s->plan ? [
                'id' => $s->plan->id,
                'name' => $s->plan->name,
                'slug' => $s->plan->slug,
                'duration_days' => $s->plan->duration_days,
                'is_unlimited' => $isUnlimited,
                'includes_flashcards' => (bool) $s->plan->includes_flashcards,
                'price' => $s->plan->price,
                'currency' => $s->plan->currency,
            ] : null,
            'started_at' => $started?->toIso8601String(),
            'expires_at' => $expires?->toIso8601String(),
            'days_remaining' => $daysRemaining,
            'days_total' => $daysTotal,
            'progress_pct' => $daysTotal ? (int) min(100, max(0, round((1 - $daysRemaining / $daysTotal) * 100))) : 0,
            'payment_provider' => $s->payment_provider,
            'payment_reference' => $s->payment_reference,
            'amount_paid' => $s->amount_paid,
            'currency' => $s->currency,
            'cancelled_at' => $s->cancelled_at?->toIso8601String(),
            'notes' => $s->notes,
            'specialties' => $s->relationLoaded('specialties')
                ? $s->specialties->map(fn ($sp) => ['id' => $sp->id, 'name' => $sp->name, 'code' => $sp->code])
                : [],
        ];
    }

    private function serializeQuestionFull(Question $q): array
    {
        return [
            'id' => $q->id,
            'code_number' => $q->code_number,
            'statement' => $q->statement,
            'options' => $q->options,
            'correct_answer' => $q->correct_answer,
            'correct_justification' => $q->correct_justification,
            'incorrect_justification' => $q->incorrect_justification,
            'catedra' => $q->catedra,
            'subtopic' => $q->subtopic,
            'source_file' => $q->source_file,
            'is_active' => (bool) $q->is_active,
            'specialty' => $q->specialty?->only(['id', 'name', 'code']),
            'topic' => $q->topic?->only(['id', 'name']),
            'section' => $q->section?->only(['id', 'name']),
            'updated_at' => $q->updated_at?->toIso8601String(),
        ];
    }
}
