<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Specialty;
use App\Models\User;
use App\Models\UserDailyQuota;
use App\Models\UserSubscription;
use Carbon\Carbon;

/**
 * Servicio central de acceso por suscripción.
 *
 * Reglas:
 *  - Si el user tiene una UserSubscription activa y vigente:
 *      - Plan Full (max_specialties = null)  → acceso a TODAS las specialties.
 *      - Plan con max_specialties != null    → solo las specialties del pivote.
 *  - Si NO tiene suscripción activa (free):
 *      - Elige UNA specialty (user_daily_quotas.daily_specialty_id).
 *      - Dentro de esa specialty tiene un límite diario de FREE_DAILY_LIMIT preguntas.
 *
 * El service NO descuenta cuota. Solo resuelve el acceso.
 * El conteo real lo lleva UserDailyQuota.
 */
class SubscriptionService
{
    /** Límite diario para usuarios sin plan pago. */
    public const FREE_DAILY_LIMIT = 5;

    /**
     * Resuelve si el user puede acceder a una specialty.
     *
     * Reglas:
     *  - Admins: bypass total (acceso a todo, sin chequear plan).
     *  - Plan pago activo: el service valida según las specialties del plan.
     *  - Free: 1 specialty elegida + 5 preguntas/día.
     *
     * @return array{allowed: bool, reason: string, plan?: UserSubscription}
     */
    public function checkSpecialtyAccess(User $user, int $specialtyId): array
    {
        // 0) Admins tienen acceso irrestricto (no necesitan plan)
        if ($user->hasRole('admin')) {
            return [
                'allowed' => true,
                'reason'  => 'admin_bypass',
            ];
        }

        $subscription = $user->activeSubscription;
        if ($subscription) {
            $subscription->loadMissing('plan', 'specialties');
        }

        // 1) Plan pago activo
        if ($subscription) {
            return $this->resolvePaidAccess($subscription, $specialtyId);
        }

        // 1.5) Fallback: flag legacy is_premium con premium_until vigente.
        // Usuarios que quedaron con el flag viejo (activado desde el panel
        // admin con el toggle antiguo) siguen teniendo acceso hasta que
        // se les cree una suscripción real o se venza el premium_until.
        if ($this->hasLegacyPremium($user)) {
            return [
                'allowed' => true,
                'reason'  => 'legacy_premium',
            ];
        }

        // 2) Free: validar specialty elegida + cuota
        return $this->resolveFreeAccess($user, $specialtyId);
    }

    /**
     * Chequea si el user tiene el flag legacy `is_premium = true` y
     * `premium_until` vigente. Usado como fallback mientras se
     * migra al sistema de suscripciones nuevo.
     */
    public function hasLegacyPremium(User $user): bool
    {
        if (! $user->is_premium) {
            return false;
        }
        if ($user->premium_until && $user->premium_until->isPast()) {
            return false; // ya venció
        }
        return true;
    }

    protected function resolvePaidAccess(UserSubscription $sub, int $specialtyId): array
    {
        $plan = $sub->plan;

        if (! $plan) {
            return [
                'allowed' => false,
                'reason'  => 'subscription_plan_missing',
            ];
        }

        // Plan Full → todas
        if ($plan->isUnlimited()) {
            return [
                'allowed'      => true,
                'reason'       => 'full_plan',
                'plan'         => $sub,
                'subscription' => $sub,
            ];
        }

        // Plan con specialties específicas
        $hasIt = $sub->specialties()->where('specialties.id', $specialtyId)->exists();

        if (! $hasIt) {
            return [
                'allowed'      => false,
                'reason'       => 'specialty_not_in_plan',
                'plan'         => $sub,
                'subscription' => $sub,
            ];
        }

        return [
            'allowed'      => true,
            'reason'       => 'in_plan',
            'plan'         => $sub,
            'subscription' => $sub,
        ];
    }

    protected function resolveFreeAccess(User $user, int $specialtyId): array
    {
        $quota = $this->getOrCreateQuota($user);

        // Si nunca eligió specialty, no tiene acceso a nada todavía
        if (! $quota->daily_specialty_id) {
            return [
                'allowed'        => false,
                'reason'         => 'free_no_specialty_chosen',
                'quota'          => $quota,
                'free_limit'     => self::FREE_DAILY_LIMIT,
            ];
        }

        // Si eligió una specialty distinta a la pedida → no accede
        if ((int) $quota->daily_specialty_id !== (int) $specialtyId) {
            return [
                'allowed'    => false,
                'reason'     => 'free_specialty_mismatch',
                'chosen'     => $quota->daily_specialty_id,
                'quota'      => $quota,
                'free_limit' => self::FREE_DAILY_LIMIT,
            ];
        }

        // Misma specialty: validar cuota
        $this->refreshDailyCounter($quota);

        if ($quota->isExhausted()) {
            return [
                'allowed'         => false,
                'reason'          => 'free_quota_exhausted',
                'quota'           => $quota,
                'free_limit'      => self::FREE_DAILY_LIMIT,
                'reset_at'        => $quota->quota_reset_at,
                'seconds_to_reset'=> $quota->quota_reset_at
                    ? max(0, now()->diffInSeconds($quota->quota_reset_at, false))
                    : null,
            ];
        }

        return [
            'allowed'    => true,
            'reason'     => 'free_within_quota',
            'quota'      => $quota,
            'free_limit' => self::FREE_DAILY_LIMIT,
        ];
    }

    public function getOrCreateQuota(User $user): UserDailyQuota
    {
        $quota = $user->quota;
        if (! $quota) {
            $quota = UserDailyQuota::create([
                'user_id'                 => $user->id,
                'questions_answered_today'=> 0,
                'daily_limit'             => self::FREE_DAILY_LIMIT,
                'quota_date'              => null,
                'quota_reset_at'          => null,
            ]);
        }
        return $quota;
    }

    /**
     * Resetea el contador diario si cambió el día.
     */
    public function refreshDailyCounter(UserDailyQuota $quota): void
    {
        $today = Carbon::today();

        if ($quota->quota_date === null || $quota->quota_date->lt($today)) {
            $quota->update([
                'quota_date'               => $today,
                'questions_answered_today' => 0,
                'quota_reset_at'           => null,
            ]);
        }

        // Si la cuota está agotada pero el reset ya pasó, también resetea
        if ($quota->quota_reset_at && $quota->quota_reset_at->isPast()) {
            $quota->update([
                'quota_date'               => $today,
                'questions_answered_today' => 0,
                'quota_reset_at'           => null,
            ]);
        }
    }

    /**
     * Registra una respuesta contestada (incrementa el contador).
     * Si llega al límite, setea quota_reset_at = +24h.
     */
    public function recordAnswer(User $user, int $specialtyId): UserDailyQuota
    {
        $subscription = $user->activeSubscription;
        if ($subscription) {
            // Pago: no descuenta cuota
            return $this->getOrCreateQuota($user);
        }

        $quota = $this->getOrCreateQuota($user);
        $this->refreshDailyCounter($quota);

        // Si cambió de specialty respecto a la elegida, no descuenta
        if ((int) $quota->daily_specialty_id !== (int) $specialtyId) {
            return $quota;
        }

        $newCount = $quota->questions_answered_today + 1;
        $resetAt  = null;

        if ($newCount >= $quota->daily_limit) {
            $resetAt = now()->addHours(24);
        }

        $quota->update([
            'questions_answered_today' => $newCount,
            'quota_reset_at'           => $resetAt,
        ]);

        return $quota;
    }

    /**
     * Crea una solicitud de suscripción (status='pending').
     * El user todavía NO tiene acceso; queda esperando que un admin apruebe.
     * Si ya tiene una solicitud pending, la devuelve sin crear duplicado.
     */
    public function requestSubscription(
        User $user,
        Plan $plan,
        array $specialtyIds = [],
        ?string $notes = null,
    ): UserSubscription {
        // Si ya tiene una solicitud pending del mismo plan, la devolvemos
        $existing = $user->subscriptions()
            ->where('status', 'pending')
            ->where('plan_id', $plan->id)
            ->first();

        if ($existing) {
            // Actualizamos las specialties por si eligió distintas
            if (! $plan->isUnlimited() && ! empty($specialtyIds)) {
                $existing->specialties()->sync($specialtyIds);
            }
            if ($notes) {
                $existing->update(['notes' => $notes]);
            }
            return $existing->fresh(['plan', 'specialties']);
        }

        return tap(UserSubscription::create([
            'user_id'         => $user->id,
            'plan_id'         => $plan->id,
            'status'          => 'pending',
            'requested_at'    => now(),
            'started_at'      => now(), // dummy; se pisa en approve()
            'expires_at'      => now(), // dummy; se pisa en approve()
            'payment_provider'=> 'whatsapp',
            'currency'        => $plan->currency,
            'notes'           => $notes,
        ]), function (UserSubscription $sub) use ($plan, $specialtyIds) {
            if (! $plan->isUnlimited() && ! empty($specialtyIds)) {
                $sub->specialties()->sync($specialtyIds);
            }
        })->fresh(['plan', 'specialties']);
    }

    /**
     * Aprueba una solicitud pending. La pasa a active con fecha real.
     * Cancela cualquier suscripción activa anterior del user.
     */
    public function approveRequest(
        UserSubscription $sub,
        array $specialtyIds = [],
        ?User $approvedBy = null,
        ?int $amountPaid = null,
        ?string $notes = null,
    ): UserSubscription {
        $user = $sub->user;
        $plan = $sub->plan;

        // Cancelar suscripciones activas previas
        $user->subscriptions()
            ->where('status', 'active')
            ->where('id', '!=', $sub->id)
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $now = now();
        $expiresAt = $now->copy()->addDays($plan->duration_days);
        $sub->update([
            'status'               => 'active',
            'started_at'           => $now,
            'expires_at'           => $expiresAt,
            'approved_at'          => $now,
            'approved_by_user_id'  => $approvedBy?->id,
            'amount_paid'          => $amountPaid ?? $plan->price,
            'rejection_reason'     => null,
            'notes'                => $notes ?? $sub->notes,
        ]);

        // Sincronizar specialties elegidas
        if (! $plan->isUnlimited()) {
            $sub->specialties()->sync($specialtyIds);
        } else {
            $sub->specialties()->detach();
        }

        // Compat: sincronizar flags legacy del User
        $user->update([
            'is_premium'    => true,
            'premium_until' => $expiresAt,
        ]);

        return $sub->fresh(['plan', 'specialties', 'approvedBy']);
    }

    /**
     * Rechaza una solicitud pending.
     */
    public function rejectRequest(
        UserSubscription $sub,
        string $reason,
        ?User $rejectedBy = null,
    ): UserSubscription {
        $sub->update([
            'status'             => 'rejected',
            'rejection_reason'   => $reason,
            'approved_by_user_id'=> $rejectedBy?->id,
            'approved_at'        => now(),
        ]);

        return $sub->fresh(['plan', 'specialties', 'approvedBy']);
    }

    /**
     * Activa una suscripción nueva directamente (sin pasar por pending).
     * Útil para que el admin active manualmente sin que el user haya solicitado.
     * Cancela cualquier suscripción activa anterior del user.
     */
    public function activate(
        User $user,
        Plan $plan,
        array $specialtyIds,
        ?User $activatedBy = null,
        ?string $paymentProvider = 'manual',
        ?string $paymentReference = null,
        ?int $amountPaid = null,
        ?string $notes = null,
    ): UserSubscription {
        // Cancelar suscripciones activas previas
        $user->subscriptions()
            ->where('status', 'active')
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $now = now();
        $expiresAt = $now->copy()->addDays($plan->duration_days);
        $sub = UserSubscription::create([
            'user_id'            => $user->id,
            'plan_id'            => $plan->id,
            'requested_at'       => $now,
            'started_at'         => $now,
            'expires_at'         => $expiresAt,
            'approved_at'        => $now,
            'status'             => 'active',
            'payment_provider'   => $paymentProvider,
            'payment_reference'  => $paymentReference,
            'amount_paid'        => $amountPaid ?? $plan->price,
            'currency'           => $plan->currency,
            'activated_by_user_id' => $activatedBy?->id,
            'approved_by_user_id'  => $activatedBy?->id,
            'notes'              => $notes,
        ]);

        // Full: no necesita specialties
        if (! $plan->isUnlimited() && ! empty($specialtyIds)) {
            $sub->specialties()->sync($specialtyIds);
        }

        // Compat: sincronizar flags legacy del User (is_premium, premium_until)
        // para que el QuotaService viejo siga funcionando.
        $user->update([
            'is_premium'    => true,
            'premium_until' => $expiresAt,
        ]);

        return $sub->fresh(['plan', 'specialties']);
    }
}
