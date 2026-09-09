<?php

namespace App\Services;

use App\Models\Specialty;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserDailyQuota;
use Carbon\Carbon;

class QuotaService
{
    public const FREE_LIMIT_PER_SPECIALTY = 4;
    public const FREE_TOTAL_LIMIT = 12;

    /**
     * Comprueba si el usuario tiene una suscripción Premium activa.
     */
    public function isPremium(User $user): bool
    {
        if (! $user->is_premium) {
            return false;
        }

        if ($user->premium_until !== null && $user->premium_until->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Comprueba si una función específica está permitida para el usuario.
     * Features: 'study', 'errors', 'flashcards', 'bookmarks'.
     */
    public function isFeatureAllowed(User $user, string $feature): bool
    {
        if ($this->isPremium($user)) {
            return true;
        }

        // Banco de Fallos y Flashcards clínicas son exclusivos de cuentas Premium
        if (in_array($feature, ['errors', 'flashcards'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Obtiene la cantidad de preguntas respondidas por el usuario en una especialidad.
     */
    public function getAnsweredCountForSpecialty(User $user, int $specialtyId): int
    {
        return UserAnswer::where('user_id', $user->id)
            ->whereHas('question', fn ($q) => $q->where('specialty_id', $specialtyId))
            ->count();
    }

    /**
     * Obtiene el total de preguntas respondidas por el usuario en toda la plataforma.
     */
    public function getTotalAnsweredCount(User $user): int
    {
        return UserAnswer::where('user_id', $user->id)->count();
    }

    /**
     * Devuelve true si el usuario puede responder una pregunta en una especialidad dada.
     */
    public function canAnswerSpecialty(User $user, int $specialtyId): bool
    {
        if ($this->isPremium($user)) {
            return true;
        }

        if ($this->getTotalAnsweredCount($user) >= self::FREE_TOTAL_LIMIT) {
            return false;
        }

        if ($this->getAnsweredCountForSpecialty($user, $specialtyId) >= self::FREE_LIMIT_PER_SPECIALTY) {
            return false;
        }

        return true;
    }

    /**
     * Devuelve true si el usuario puede responder preguntas.
     */
    public function canAnswer(User $user, ?int $specialtyId = null): bool
    {
        if ($this->isPremium($user)) {
            return true;
        }

        if ($this->getTotalAnsweredCount($user) >= self::FREE_TOTAL_LIMIT) {
            return false;
        }

        if ($specialtyId !== null) {
            return $this->canAnswerSpecialty($user, $specialtyId);
        }

        return true;
    }

    /**
     * Devuelve el estado actual de la cuota del usuario, con desglose por especialidad.
     */
    public function getQuotaStatus(User $user): array
    {
        $isPremium = $this->isPremium($user);
        $totalAnswered = $this->getTotalAnsweredCount($user);

        $specialties = Specialty::orderBy('sort_order')->orderBy('name')->get();
        $specialtyQuotas = [];

        foreach ($specialties as $s) {
            $answeredInSpec = $this->getAnsweredCountForSpecialty($user, $s->id);
            $limitInSpec = $isPremium ? null : self::FREE_LIMIT_PER_SPECIALTY;
            $remainingInSpec = $isPremium ? null : max(0, self::FREE_LIMIT_PER_SPECIALTY - $answeredInSpec);
            $isLockedInSpec = ! $isPremium && ($answeredInSpec >= self::FREE_LIMIT_PER_SPECIALTY || $totalAnswered >= self::FREE_TOTAL_LIMIT);

            $specialtyQuotas[$s->id] = [
                'specialty_id' => $s->id,
                'name' => $s->name,
                'answered' => $answeredInSpec,
                'limit' => $limitInSpec,
                'remaining' => $remainingInSpec,
                'is_locked' => $isLockedInSpec,
            ];
        }

        $totalLimit = $isPremium ? null : self::FREE_TOTAL_LIMIT;
        $totalRemaining = $isPremium ? null : max(0, self::FREE_TOTAL_LIMIT - $totalAnswered);
        $isLocked = ! $isPremium && $totalAnswered >= self::FREE_TOTAL_LIMIT;

        return [
            'is_premium' => $isPremium,
            'free_limit_per_specialty' => self::FREE_LIMIT_PER_SPECIALTY,
            'free_total_limit' => self::FREE_TOTAL_LIMIT,
            'total_answered' => $totalAnswered,
            'total_remaining' => $totalRemaining,
            'is_locked' => $isLocked,
            'specialties' => $specialtyQuotas,
            'features' => [
                'study' => true,
                'errors' => $isPremium,
                'flashcards' => $isPremium,
                'bookmarks' => true,
            ],
            // Compatibilidad para vistas que leen daily_limit/remaining_today
            'daily_limit' => $totalLimit,
            'answered_today' => $totalAnswered,
            'remaining_today' => $totalRemaining,
            'seconds_remaining' => 0,
            'reset_at' => null,
        ];
    }

    /**
     * Mantenido para compatibilidad con llamadas existentes.
     */
    public function consumeQuota(User $user): ?UserDailyQuota
    {
        return null;
    }

    /**
     * Resetea la cuota del usuario (usado en testing).
     */
    public function resetQuota(User $user): void
    {
        UserAnswer::where('user_id', $user->id)->delete();
    }
}
