import { defineStore } from 'pinia';
import axios from 'axios';

export interface SpecialtyQuota {
    specialty_id: number;
    name: string;
    answered: number;
    limit: number | null;
    remaining: number | null;
    is_locked: boolean;
}

export interface QuotaState {
    is_premium: boolean;
    free_limit_per_specialty: number;
    free_total_limit: number;
    total_answered: number;
    total_remaining: number | null;
    is_locked: boolean;
    specialties?: Record<number, SpecialtyQuota>;
    features?: {
        study: boolean;
        errors: boolean;
        flashcards: boolean;
        bookmarks: boolean;
    };
    daily_limit: number | null;
    answered_today: number | null;
    remaining_today: number | null;
    reset_at: string | null;
    seconds_remaining: number;
}

export interface StreakState {
    current_daily_streak: number;
    max_daily_streak: number;
    current_correct_streak: number;
    max_correct_streak: number;
    last_activity_date: string | null;
}

interface State {
    quota: QuotaState | null;
    streak: StreakState | null;
    loading: boolean;
    error: string | null;
}

export const useQuotaStore = defineStore('quota', {
    state: (): State => ({
        quota: null,
        streak: null,
        loading: false,
        error: null,
    }),

    getters: {
        isPremium: (s) => s.quota?.is_premium ?? false,
        isLocked: (s) => s.quota?.is_locked ?? false,
        totalAnswered: (s) => s.quota?.total_answered ?? s.quota?.answered_today ?? 0,
        freeTotalLimit: (s) => s.quota?.free_total_limit ?? 12,
        freeLimitPerSpecialty: (s) => s.quota?.free_limit_per_specialty ?? 4,
        canAccessErrors: (s) => s.quota?.is_premium ?? false,
        canAccessFlashcards: (s) => s.quota?.is_premium ?? false,
        remainingToday: (s) => s.quota?.remaining_today ?? 0,
        answeredToday: (s) => s.quota?.answered_today ?? 0,
        currentCorrectStreak: (s) => s.streak?.current_correct_streak ?? 0,
        currentDailyStreak: (s) => s.streak?.current_daily_streak ?? 0,
    },

    actions: {
        async fetch() {
            this.loading = true;
            try {
                const { data } = await axios.get('/user/quota');
                this.quota = data.quota;
                this.streak = data.streak;
            } catch (e: any) {
                this.error = e.response?.data?.message || 'Error al obtener cuota';
            } finally {
                this.loading = false;
            }
        },

        async togglePremium(): Promise<void> {
            const { data } = await axios.post('/user/premium-toggle');
            this.quota = data.quota;
        },

        /** Llamado por QuizStore después de cada respuesta */
        applyServerQuota(quota: QuotaState, streak?: StreakState) {
            this.quota = quota;
            if (streak) this.streak = streak;
        },

        /**
         * No-op mantenido por compat. El timer del countdown ahora vive
         * local en CooldownModal.vue (causaba "startTime undefined" en prod).
         */
        startTimer() {},
        stopTimer() {},

        /** Llamado por el Navbar al desmontar para limpiar estado. */
        cleanup() {
            this.quota = null;
            this.streak = null;
        },
    },
});
