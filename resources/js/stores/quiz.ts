import { defineStore } from 'pinia';
import axios from 'axios';
import { useQuotaStore, type QuotaState, type StreakState } from './quota';

export interface QuestionOption {
    key: string;
    text: string;
}

export interface Question {
    id: number;
    code_number: number;
    statement: string;
    options: QuestionOption[];
    correct_answer: string;
    catedra: string | null;
    subtopic: string | null;
    source_file: string | null;
    specialty?: { id: number; name: string; code: string };
    topic?: { id: number; name: string; code: string };
    section?: { id: number; name: string; code: string };
    error_count?: number;
    is_bookmarked?: boolean;
}

export interface AnswerFeedback {
    is_correct: boolean;
    correct_answer: string;
    correct_justification: string | null;
    incorrect_justification: string | null;
    source_justification: string | null;
    source_file: string | null;
    catedra: string | null;
    streak: StreakState;
    quota: QuotaState;
}

interface State {
    questions: Question[];
    index: number;
    selected: string | null;
    feedback: AnswerFeedback | null;
    crossedOut: Set<string>;
    loading: boolean;
    error: string | null;
    history: { question_id: number; selected: string; is_correct: boolean }[];
    mode: 'free' | 'errors' | 'review' | null;
    lockedModalShown: boolean;
}

export const useQuizStore = defineStore('quiz', {
    state: (): State => ({
        questions: [],
        index: 0,
        selected: null,
        feedback: null,
        crossedOut: new Set(),
        loading: false,
        error: null,
        history: [],
        mode: null,
        lockedModalShown: false,
    }),

    getters: {
        current: (s): Question | null => s.questions[s.index] ?? null,
        progress: (s) => `${s.index + 1}/${s.questions.length}`,
        isFinished: (s) => s.index >= s.questions.length,
        sessionCorrect: (s) => s.history.filter((h) => h.is_correct).length,
        sessionWrong: (s) => s.history.filter((h) => !h.is_correct).length,
    },

    actions: {
        async fetchStudy(params: { specialty_id?: number; topic_id?: number; mode?: 'free' | 'review'; limit?: number }) {
            this.loading = true;
            this.error = null;
            this.mode = params.mode ?? 'free';
            try {
                const { data } = await axios.get('/questions/study', { params });
                this.questions = Array.isArray(data?.questions) ? data.questions : [];
                this.index = 0;
                this.history = [];
                this.selected = null;
                this.feedback = null;
                this.crossedOut = new Set();
                const quota = useQuotaStore();
                if (data?.quota) quota.applyServerQuota(data.quota);
            } catch (e: any) {
                this.questions = [];
                this.error = e.response?.data?.message || 'Error al cargar preguntas';
            } finally {
                this.loading = false;
            }
        },

        async fetchErrors(params: { specialty_id?: number; limit?: number }) {
            this.loading = true;
            this.error = null;
            this.mode = 'errors';
            try {
                const { data } = await axios.get('/questions/errors', { params });
                this.questions = Array.isArray(data?.questions) ? data.questions : [];
                this.index = 0;
                this.history = [];
                this.selected = null;
                this.feedback = null;
                this.crossedOut = new Set();
            } catch (e: any) {
                this.questions = [];
                this.error = e.response?.data?.message || 'Error al cargar banco de fallos';
            } finally {
                this.loading = false;
            }
        },

        selectOption(key: string) {
            if (this.feedback) return; // bloqueado durante feedback
            this.selected = key;
        },

        toggleCrossOut(key: string) {
            if (this.feedback) return;
            const next = new Set(this.crossedOut);
            if (next.has(key)) next.delete(key);
            else next.add(key);
            this.crossedOut = next;
        },

        async submitAnswer() {
            if (!this.current || !this.selected) return;
            this.loading = true;
            try {
                const { data } = await axios.post(`/questions/${this.current.id}/answer`, {
                    selected_answer: this.selected,
                    mode: this.mode,
                });
                this.feedback = data;
                this.history.push({
                    question_id: this.current.id,
                    selected: this.selected,
                    is_correct: data.is_correct,
                });
                const quota = useQuotaStore();
                quota.applyServerQuota(data.quota, data.streak);
                if (data.quota.is_locked) this.lockedModalShown = true;
            } catch (e: any) {
                if (e.response?.status === 429) {
                    this.error = e.response.data.message || 'Cuota agotada';
                    const quota = useQuotaStore();
                    if (e.response.data.quota) quota.applyServerQuota(e.response.data.quota);
                    this.lockedModalShown = true;
                } else {
                    this.error = e.response?.data?.message || 'Error al registrar respuesta';
                }
            } finally {
                this.loading = false;
            }
        },

        next() {
            this.index += 1;
            this.selected = null;
            this.feedback = null;
            this.crossedOut = new Set();
        },

        async toggleBookmark(questionId: number) {
            try {
                const { data } = await axios.post(`/questions/${questionId}/bookmark`);
                const q = this.questions.find((x) => x.id === questionId);
                if (q) q.is_bookmarked = data.bookmarked;
                return data.bookmarked as boolean;
            } catch {
                return null;
            }
        },

        reset() {
            this.questions = [];
            this.index = 0;
            this.selected = null;
            this.feedback = null;
            this.crossedOut = new Set();
            this.history = [];
            this.mode = null;
            this.lockedModalShown = false;
            this.error = null;
        },
    },
});
