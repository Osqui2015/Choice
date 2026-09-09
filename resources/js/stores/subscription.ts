import { defineStore } from 'pinia';
import axios from 'axios';

export interface Plan {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price: number;
    currency: string;
    formatted_price: string;
    duration_days: number;
    max_specialties: number | null;
    is_unlimited: boolean;
    includes_flashcards: boolean;
}

export interface SpecialtyLite {
    id: number;
    code: string;
    name: string;
    slug?: string;
    icon?: string | null;
    color?: string | null;
}

export interface PlanRequest {
    id: number;
    status: 'pending' | 'active' | 'rejected' | 'cancelled' | 'expired';
    plan: Plan;
    specialties: SpecialtyLite[];
    requested_at: string | null;
    started_at: string | null;
    expires_at: string | null;
    approved_at: string | null;
    approved_by: { id: number; name: string } | null;
    rejection_reason: string | null;
    notes: string | null;
    amount_paid: number | null;
    currency: string;
}

export interface ActiveSubscription {
    id: number;
    plan: Pick<Plan, 'id' | 'name' | 'slug' | 'includes_flashcards'>;
    specialties: SpecialtyLite[];
    started_at: string | null;
    expires_at: string | null;
    days_remaining: number;
    is_unlimited: boolean;
    amount_paid: number | null;
    currency: string;
    payment_provider: string | null;
}

export interface FreeQuota {
    chosen_specialty: number | null;
    questions_answered: number;
    daily_limit: number;
    remaining_today: number;
    is_exhausted: boolean;
    reset_at: string | null;
}

export interface WhatsAppContact {
    number: string;
    base_message: string;
}

export interface SubscriptionState {
    plans: Plan[];
    specialties: SpecialtyLite[];
    hasActive: boolean;
    active: ActiveSubscription | null;
    pending: PlanRequest | null;
    freeQuota: FreeQuota | null;
    whatsapp: WhatsAppContact | null;
    loading: boolean;
    error: string | null;
    submitting: boolean;
}

export const useSubscriptionStore = defineStore('subscription', {
    state: (): SubscriptionState => ({
        plans: [],
        specialties: [],
        hasActive: false,
        active: null,
        pending: null,
        freeQuota: null,
        whatsapp: null,
        loading: false,
        error: null,
        submitting: false,
    }),

    getters: {
        isPremium: (s) => s.hasActive,
        /** IDs de especialidades a las que el user tiene acceso */
        accessibleSpecialtyIds(): number[] {
            if (!this.active) return [];
            if (this.active.is_unlimited) return this.specialties.map(sp => sp.id);
            return this.active.specialties.map(sp => sp.id);
        },
        canAccessSpecialty: (s) => (specialtyId: number) => {
            if (s.hasActive) {
                if (s.active?.is_unlimited) return true;
                return !!s.active?.specialties.some(sp => sp.id === specialtyId);
            }
            // Free
            return s.freeQuota?.chosen_specialty === specialtyId && !s.freeQuota.is_exhausted;
        },
    },

    actions: {
        async fetchPlans(): Promise<void> {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await axios.get('/plans');
                this.plans = data.plans;
                this.specialties = data.specialties;
            } catch (e: any) {
                this.error = e.response?.data?.message || 'No se pudo cargar el catálogo.';
            } finally {
                this.loading = false;
            }
        },

        async fetchMySubscription(): Promise<void> {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await axios.get('/me/subscription');
                this.hasActive = !!data.has_active_subscription;
                this.active = data.active_subscription;
                this.pending = data.pending_request;
                this.freeQuota = data.free_quota;
                this.whatsapp = data.whatsapp_contact;
            } catch (e: any) {
                this.error = e.response?.data?.message || 'No se pudo cargar tu suscripción.';
            } finally {
                this.loading = false;
            }
        },

        /**
         * Crea una solicitud de plan. Devuelve { whatsapp_url } si todo OK.
         */
        async requestPlan(planId: number, specialtyIds: number[], notes?: string): Promise<{ whatsapp_url: string }> {
            this.submitting = true;
            this.error = null;
            try {
                const { data } = await axios.post('/me/subscription-requests', {
                    plan_id: planId,
                    specialty_ids: specialtyIds,
                    notes,
                });
                await this.fetchMySubscription();
                return { whatsapp_url: data.whatsapp_url };
            } catch (e: any) {
                const msg = e.response?.data?.message
                    || e.response?.data?.errors?.plan_id?.[0]
                    || e.response?.data?.errors?.specialty_ids?.[0]
                    || 'No se pudo crear la solicitud.';
                this.error = msg;
                throw new Error(msg);
            } finally {
                this.submitting = false;
            }
        },

        async chooseFreeSpecialty(specialtyId: number): Promise<void> {
            this.submitting = true;
            this.error = null;
            try {
                await axios.post('/me/choose-free-specialty', { specialty_id: specialtyId });
                await this.fetchMySubscription();
            } catch (e: any) {
                this.error = e.response?.data?.message || 'No se pudo elegir la materia.';
                throw this.error;
            } finally {
                this.submitting = false;
            }
        },

        // ============================================================
        // ADMIN
        // ============================================================

        async adminListRequests(status?: string): Promise<PlanRequest[]> {
            const { data } = await axios.get('/admin/subscription-requests', {
                params: status ? { status } : {},
            });
            return data.requests;
        },

        async adminApprove(requestId: number, payload: { specialty_ids?: number[]; amount_paid?: number; notes?: string }): Promise<void> {
            this.submitting = true;
            try {
                await axios.post(`/admin/subscription-requests/${requestId}/approve`, payload);
            } catch (e: any) {
                this.error = e.response?.data?.message || 'No se pudo aprobar la solicitud.';
                throw e;
            } finally {
                this.submitting = false;
            }
        },

        async adminReject(requestId: number, reason: string): Promise<void> {
            this.submitting = true;
            try {
                await axios.post(`/admin/subscription-requests/${requestId}/reject`, { reason });
            } catch (e: any) {
                this.error = e.response?.data?.message || 'No se pudo rechazar la solicitud.';
                throw e;
            } finally {
                this.submitting = false;
            }
        },
    },
});
