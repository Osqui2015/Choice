<?php

namespace App\Observers;

use App\Models\Plan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\SubscriptionService;

/**
 * Observer que activa automáticamente un plan Full a los usuarios
 * que reciben el rol admin, para que tengan acceso total sin tener
 * que pasar por el flujo de pago.
 */
class UserObserver
{
    public function __construct(protected SubscriptionService $service) {}

    /**
     * Cuando se ASIGNA el rol admin a un user (no cuando se crea),
     * le activamos plan Full por 100 años.
     */
    public function roleAttached(User $user, string $role, array $attributes = []): void
    {
        if ($role !== 'admin') return;
        $this->ensureFullPlan($user);
    }

    /**
     * Helper: asegura que el user tenga plan Full activo.
     * Si ya tiene un plan activo vigente, no hace nada.
     */
    public function ensureFullPlan(User $user): void
    {
        if ($user->activeSubscription()->exists()) {
            return; // ya tiene plan
        }

        $plan = Plan::where('slug', 'full')->where('is_active', true)->first();
        if (! $plan) {
            return; // el plan Full no existe todavía (seed no corrió)
        }

        // 100 años = plan vitalicio para admin
        $this->service->activate(
            user: $user,
            plan: $plan,
            specialtyIds: [],
            activatedBy: null,
            paymentProvider: 'admin_grant',
            paymentReference: 'admin_auto_grant',
            amountPaid: 0,
            notes: 'Acceso total automático por rol admin',
        );
    }
}
