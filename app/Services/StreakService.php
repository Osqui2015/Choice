<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserStreak;
use Carbon\Carbon;

class StreakService
{
    /**
     * Registra una respuesta y actualiza las rachas.
     * Devuelve el estado actual de las rachas.
     */
    public function recordAnswer(User $user, bool $isCorrect): array
    {
        $streak = $this->getOrCreate($user);
        $today = Carbon::today();

        // Racha de respuestas correctas seguidas.
        if ($isCorrect) {
            $streak->current_correct_streak++;
            $streak->max_correct_streak = max($streak->max_correct_streak, $streak->current_correct_streak);
        } else {
            $streak->current_correct_streak = 0;
        }

        // Racha diaria: si la última actividad fue ayer, se incrementa; si es hoy, se mantiene;
        // si fue anteayer o más, se reinicia a 1.
        if ($streak->last_activity_date === null) {
            $streak->current_daily_streak = 1;
        } elseif ($streak->last_activity_date->equalTo($today)) {
            // Ya estudió hoy, no se incrementa la racha diaria (sólo se hace una vez por día).
        } elseif ($streak->last_activity_date->equalTo($today->copy()->subDay())) {
            $streak->current_daily_streak++;
        } else {
            $streak->current_daily_streak = 1;
        }

        $streak->max_daily_streak = max($streak->max_daily_streak, $streak->current_daily_streak);
        $streak->last_activity_date = $today;
        $streak->save();

        return $this->getStreakStatsFromModel($streak);
    }

    public function getStreakStats(User $user): array
    {
        $streak = $this->getOrCreate($user);

        return $this->getStreakStatsFromModel($streak);
    }

    private function getStreakStatsFromModel(UserStreak $streak): array
    {
        return [
            'current_daily_streak' => $streak->current_daily_streak,
            'max_daily_streak' => $streak->max_daily_streak,
            'current_correct_streak' => $streak->current_correct_streak,
            'max_correct_streak' => $streak->max_correct_streak,
            'last_activity_date' => $streak->last_activity_date?->toDateString(),
        ];
    }

    private function getOrCreate(User $user): UserStreak
    {
        return UserStreak::firstOrCreate(['user_id' => $user->id]);
    }
}
