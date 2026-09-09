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
    timerHandle: number | null;
}

export const useQuotaStore = defineStore('quota', {
    state: (): State => ({
        quota: null,
        streak: null,
        loading: false,
        error: null,
        timerHandle: null,
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
        countdown: (s): string => {
            const secs = s.quota?.seconds_remaining ?? 0;
            if (secs <= 0) return '00:00:00';
            const h = Math.floor(secs / 3600);
            const m = Math.floor((secs % 3600) / 60);
            const sec = secs % 60;
            return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
        },
    },

    actions: {
        async fetch() {
            this.loading = true;
            try {
                const { data } = await axios.get('/user/quota');
                this.quota = data.quota;
                this.streak = data.streak;
                this.startTimer();
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
            this.startTimer();
        },

        startTimer() {
            this.stopTimer();
            if (!this.quota?.is_locked) return;

            // Capturamos referencia al store: dentro del setInterval,
            // `this` se pierde (sería `window`), así que usamos la variable local.
            const store = this as Required<State>;
            this.timerHandle = window.setInterval(() => {
                const q = store.quota;
                if (!q) {
                    store.stopTimer();
                    return;
                }
                if (q.seconds_remaining > 0) {
                    store.quota = { ...q, seconds_remaining: q.seconds_remaining - 1 };
                } else {
                    // Reset automático cuando llega a 0
                    store.quota = {
                        ...q,
                        is_locked: false,
                        remaining_today: q.daily_limit,
                        answered_today: 0,
                    };
                    store.stopTimer();
                }
            }, 1000);
        },

        stopTimer() {
            if (this.timerHandle) {
                clearInterval(this.timerHandle);
                this.timerHandle = null;
            }
        },

        /** Llamado por el Navbar al desmontar para evitar timers huérfanos. */
        cleanup() {
            this.stopTimer();
            this.quota = null;
            this.streak = null;
        },
    },
});
