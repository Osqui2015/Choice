<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Specialty;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    public function __construct(protected SubscriptionService $service) {}

    // ============================================================
    // USER
    // ============================================================

    /**
     * GET /api/me/subscription
     * Estado actual de suscripción del user autenticado.
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing(['subscriptions.plan', 'subscriptions.specialties']);

        $active   = $user->activeSubscription();
        $pending  = $user->subscriptions()->where('status', 'pending')->latest('requested_at')->first();
        $quota    = $this->service->getOrCreateQuota($user);
        $this->service->refreshDailyCounter($quota);

        return response()->json([
            'has_active_subscription' => $active !== null,
            'active_subscription'     => $active ? $this->serializeActive($active) : null,
            'pending_request'         => $pending ? $this->serializePending($pending) : null,
            'free_quota' => [
                'chosen_specialty'      => $quota->daily_specialty_id,
                'questions_answered'    => $quota->questions_answered_today,
                'daily_limit'           => $quota->daily_limit,
                'remaining_today'       => max(0, $quota->daily_limit - $quota->questions_answered_today),
                'is_exhausted'          => $quota->isExhausted(),
                'reset_at'              => $quota->quota_reset_at,
            ],
            'whatsapp_contact' => $this->whatsappContact(),
        ]);
    }

    /**
     * POST /api/me/subscription-requests
     * El user solicita un plan. Queda en status='pending' hasta que el admin apruebe.
     * Devuelve el link wa.me para que el user le escriba a Oscar.
     */
    public function requestSubscription(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->activeSubscription()) {
            return response()->json([
                'message' => 'Ya tenés un plan activo. Cancelalo primero para cambiar.',
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'plan_id'         => ['required', 'integer', 'exists:plans,id'],
            'specialty_ids'   => ['nullable', 'array'],
            'specialty_ids.*' => ['integer', 'exists:specialties,id'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $plan = Plan::findOrFail($request->input('plan_id'));
        $specialtyIds = $request->input('specialty_ids', []);

        if (! $plan->isUnlimited()) {
            if (count($specialtyIds) === 0) {
                return response()->json([
                    'message' => 'Elegí al menos 1 especialidad para este plan.',
                ], 422);
            }
            if (count($specialtyIds) > $plan->max_specialties) {
                return response()->json([
                    'message'         => "El plan {$plan->name} permite hasta {$plan->max_specialties} especialidades.",
                    'max_specialties' => $plan->max_specialties,
                ], 422);
            }
        }

        $sub = $this->service->requestSubscription(
            user: $user,
            plan: $plan,
            specialtyIds: $specialtyIds,
            notes: $request->input('notes'),
        );

        $whatsappLink = $this->buildWhatsappLink($user, $plan, $sub->fresh(['specialties']));

        return response()->json([
            'message'      => 'Solicitud creada. Escribinos por WhatsApp para coordinar el pago y la activación.',
            'request'      => $this->serializePending($sub->fresh(['plan', 'specialties'])),
            'whatsapp_url' => $whatsappLink,
        ], 201);
    }

    /**
     * GET /api/me/subscriptions
     * Alias: devuelve el historial de solicitudes del user.
     */
    public function history(Request $request): JsonResponse
    {
        return $this->myRequests($request);
    }

    /**
     * GET /api/me/subscription-requests
     * El user ve sus solicitudes (historial).
     */
    public function myRequests(Request $request): JsonResponse
    {
        $subs = $request->user()
            ->subscriptions()
            ->with(['plan', 'specialties', 'approvedBy'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'requests' => $subs->map(fn ($s) => $this->serializeRequest($s)),
        ]);
    }

    /**
     * POST /api/me/choose-free-specialty
     * El usuario free elige qué materia quiere para sus preguntas diarias.
     * Solo permitido si NO tiene plan pago activo.
     */
    public function chooseFreeSpecialty(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->activeSubscription()) {
            return response()->json([
                'message' => 'Tenés un plan activo, no necesitás elegir specialty free.',
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'specialty_id' => ['required', 'integer', 'exists:specialties,id'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $quota = $this->service->getOrCreateQuota($user);
        $previous = $quota->daily_specialty_id;

        $quota->update(['daily_specialty_id' => $request->input('specialty_id')]);
        $quota->update([
            'questions_answered_today' => 0,
            'quota_date'               => now()->toDateString(),
            'quota_reset_at'           => null,
        ]);

        $specialty = Specialty::find($request->input('specialty_id'));

        return response()->json([
            'message'   => 'Specialty free actualizada.',
            'previous'  => $previous,
            'specialty' => [
                'id'   => $specialty->id,
                'name' => $specialty->name,
                'code' => $specialty->code,
            ],
            'quota'     => [
                'questions_answered' => 0,
                'daily_limit'        => $quota->daily_limit,
                'remaining_today'    => $quota->daily_limit,
            ],
        ]);
    }

    // ============================================================
    // ADMIN
    // ============================================================

    /**
     * GET /api/admin/subscription-requests?status=pending
     */
    public function adminListRequests(Request $request): JsonResponse
    {
        $query = UserSubscription::with(['user:id,name,email', 'plan', 'specialties', 'approvedBy']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $subs = $query
            ->orderByRaw("CASE status
                WHEN 'pending' THEN 0
                WHEN 'active'  THEN 1
                WHEN 'rejected' THEN 2
                WHEN 'cancelled' THEN 3
                WHEN 'expired' THEN 4
                ELSE 5 END")
            ->orderByDesc('created_at')
            ->limit((int) $request->query('limit', 100))
            ->get();

        return response()->json([
            'requests' => $subs->map(fn ($s) => array_merge(
                $this->serializeRequest($s),
                [
                    'user' => [
                        'id'    => $s->user?->id,
                        'name'  => $s->user?->name,
                        'email' => $s->user?->email,
                    ],
                ]
            )),
        ]);
    }

    /**
     * POST /api/admin/subscription-requests/{id}/approve
     */
    public function adminApprove(Request $request, int $id): JsonResponse
    {
        $sub = UserSubscription::with('plan')->findOrFail($id);

        if (! $sub->isPending()) {
            return response()->json([
                'message' => "La solicitud no está pendiente (status actual: {$sub->status}).",
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'specialty_ids'  => ['nullable', 'array'],
            'specialty_ids.*'=> ['integer', 'exists:specialties,id'],
            'amount_paid'    => ['nullable', 'integer', 'min:0'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $plan = $sub->plan;
        $specialtyIds = $request->input('specialty_ids');

        if ($specialtyIds === null) {
            $specialtyIds = $sub->specialties()->pluck('specialties.id')->toArray();
        }

        if (! $plan->isUnlimited()) {
            if (count($specialtyIds) === 0) {
                return response()->json([
                    'message' => 'Tenés que elegir al menos 1 especialidad antes de aprobar.',
                ], 422);
            }
            if (count($specialtyIds) > $plan->max_specialties) {
                return response()->json([
                    'message' => "El plan {$plan->name} permite hasta {$plan->max_specialties} especialidades.",
                ], 422);
            }
        }

        $approved = $this->service->approveRequest(
            sub: $sub,
            specialtyIds: $specialtyIds,
            approvedBy: $request->user(),
            amountPaid: $request->input('amount_paid'),
            notes: $request->input('notes'),
        );

        return response()->json([
            'message'      => 'Solicitud aprobada. El usuario ya tiene acceso.',
            'subscription' => $this->serializeActive($approved),
        ]);
    }

    /**
     * POST /api/admin/subscription-requests/{id}/reject
     */
    public function adminReject(Request $request, int $id): JsonResponse
    {
        $sub = UserSubscription::findOrFail($id);

        if (! $sub->isPending()) {
            return response()->json([
                'message' => "La solicitud no está pendiente (status actual: {$sub->status}).",
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $rejected = $this->service->rejectRequest(
            sub: $sub,
            reason: $request->input('reason'),
            rejectedBy: $request->user(),
        );

        return response()->json([
            'message' => 'Solicitud rechazada.',
            'request' => $this->serializeRequest($rejected),
        ]);
    }

    /**
     * POST /api/admin/users/{user}/subscriptions
     * Activa manualmente sin que el user haya solicitado.
     * Útil para casos donde vos activás sin que el user pase por el flujo.
     */
    public function adminActivate(Request $request, int $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan_id'          => ['required', 'integer', 'exists:plans,id'],
            'specialty_ids'    => ['array'],
            'specialty_ids.*'  => ['integer', 'exists:specialties,id'],
            'payment_provider' => ['nullable', 'string'],
            'payment_reference'=> ['nullable', 'string', 'max:255'],
            'amount_paid'      => ['nullable', 'integer', 'min:0'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $plan = Plan::findOrFail($request->input('plan_id'));

        if (! $plan->isUnlimited()) {
            $chosen = $request->input('specialty_ids', []);
            if (count($chosen) === 0) {
                return response()->json([
                    'message' => 'Este plan requiere que elijas al menos 1 especialidad.',
                ], 422);
            }
            if (count($chosen) > $plan->max_specialties) {
                return response()->json([
                    'message'         => 'Este plan permite como máximo ' . $plan->max_specialties . ' especialidades.',
                    'max_specialties' => $plan->max_specialties,
                    'received'        => count($chosen),
                ], 422);
            }
        }

        $targetUser = User::findOrFail($userId);
        $sub = $this->service->activate(
            user: $targetUser,
            plan: $plan,
            specialtyIds: $request->input('specialty_ids', []),
            activatedBy: $request->user(),
            paymentProvider: $request->input('payment_provider', 'whatsapp'),
            paymentReference: $request->input('payment_reference'),
            amountPaid: $request->input('amount_paid'),
            notes: $request->input('notes'),
        );

        return response()->json([
            'message'      => 'Suscripción activada correctamente.',
            'subscription' => $this->serializeActive($sub),
        ], 201);
    }

    /**
     * POST /api/admin/users/{user}/subscriptions/{subscription}/cancel
     */
    public function adminCancel(Request $request, int $userId, int $subscriptionId): JsonResponse
    {
        $sub = UserSubscription::where('user_id', $userId)
            ->where('id', $subscriptionId)
            ->firstOrFail();

        $sub->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return response()->json([
            'message' => 'Suscripción cancelada.',
            'request' => $this->serializeRequest($sub->fresh()),
        ]);
    }

    // ============================================================
    // HELPERS
    // ============================================================

    protected function serializeActive(UserSubscription $sub): array
    {
        return [
            'id'                => $sub->id,
            'plan'              => [
                'id'                  => $sub->plan->id,
                'name'                => $sub->plan->name,
                'slug'                => $sub->plan->slug,
                'includes_flashcards' => $sub->plan->includes_flashcards,
            ],
            'specialties'       => $sub->specialties->map(fn ($s) => [
                'id'   => $s->id,
                'name' => $s->name,
                'code' => $s->code,
            ]),
            'started_at'        => $sub->started_at,
            'expires_at'        => $sub->expires_at,
            'days_remaining'    => $sub->daysRemaining(),
            'is_unlimited'      => $sub->plan->isUnlimited(),
            'amount_paid'       => $sub->amount_paid,
            'currency'          => $sub->currency,
            'payment_provider'  => $sub->payment_provider,
        ];
    }

    protected function serializePending(UserSubscription $sub): array
    {
        return $this->serializeRequest($sub);
    }

    protected function serializeRequest(UserSubscription $sub): array
    {
        return [
            'id'                => $sub->id,
            'status'            => $sub->status,
            'plan'              => [
                'id'                  => $sub->plan->id,
                'name'                => $sub->plan->name,
                'slug'                => $sub->plan->slug,
                'price'               => $sub->plan->price,
                'currency'            => $sub->plan->currency,
                'formatted_price'     => $sub->plan->formattedPrice(),
                'duration_days'       => $sub->plan->duration_days,
                'max_specialties'     => $sub->plan->max_specialties,
                'is_unlimited'        => $sub->plan->isUnlimited(),
            ],
            'specialties'       => $sub->specialties->map(fn ($s) => [
                'id'   => $s->id,
                'name' => $s->name,
                'code' => $s->code,
            ]),
            'requested_at'      => $sub->requested_at,
            'started_at'        => $sub->started_at,
            'expires_at'        => $sub->expires_at,
            'approved_at'       => $sub->approved_at,
            'approved_by'       => $sub->approvedBy ? [
                'id'   => $sub->approvedBy->id,
                'name' => $sub->approvedBy->name,
            ] : null,
            'rejection_reason'  => $sub->rejection_reason,
            'notes'             => $sub->notes,
            'amount_paid'       => $sub->amount_paid,
            'currency'          => $sub->currency,
        ];
    }

    protected function whatsappContact(): array
    {
        return [
            'number'       => config('services.whatsapp.premium_number'),
            'base_message' => config('services.whatsapp.premium_message'),
        ];
    }

    protected function buildWhatsappLink(User $user, Plan $plan, UserSubscription $sub): string
    {
        $number = config('services.whatsapp.premium_number');
        $base   = config('services.whatsapp.premium_message', 'Hola! Quiero activar mi suscripcion.');

        $specialties = $sub->specialties->pluck('name')->implode(', ') ?: 'A definir';

        $msg = $base . "\n\n"
            . "• Nombre: {$user->name}\n"
            . "• Email: {$user->email}\n"
            . "• Plan: {$plan->name} ({$plan->formattedPrice()}/{$plan->duration_days} días)\n"
            . "• Materias: {$specialties}\n"
            . "• Solicitud #{$sub->id}";

        return 'https://wa.me/' . $number . '?text=' . urlencode($msg);
    }
}
