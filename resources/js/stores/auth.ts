import { defineStore } from 'pinia';
import axios from 'axios';

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    roles: string[];
}

interface AuthState {
    token: string | null;
    user: AuthUser | null;
    loading: boolean;
    error: string | null;
}

export const useAuthStore = defineStore('auth', {
    state: (): AuthState => ({
        token: localStorage.getItem('auth_token'),
        user: null,
        loading: false,
        error: null,
    }),

    getters: {
        isAuthenticated: (state) => Boolean(state.token),
        isAdmin: (state) => state.user?.roles.includes('admin') ?? false,
        isUsuario: (state) => state.user?.roles.includes('usuario') ?? false,
        homeRoute(): string {
            return 'study';
        },
    },

    actions: {
        async login(email: string, password: string): Promise<boolean> {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await axios.post('/auth/login', { email, password });
                this.token = data.token;
                this.user = data.user;
                localStorage.setItem('auth_token', data.token);
                axios.defaults.headers.common['Authorization'] = `Bearer ${data.token}`;
                return true;
            } catch (e: any) {
                this.error = e.response?.data?.message
                    || e.response?.data?.errors?.email?.[0]
                    || 'No se pudo iniciar sesión';
                return false;
            } finally {
                this.loading = false;
            }
        },

        async fetchMe(): Promise<void> {
            if (!this.token) return;
            axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
            const { data } = await axios.get('/auth/me');
            this.user = data;
        },

        async logout(): Promise<void> {
            try {
                if (this.token) {
                    await axios.post('/auth/logout');
                }
            } catch {
                // ignorar
            } finally {
                this.clear();
            }
        },

        clear() {
            this.token = null;
            this.user = null;
            localStorage.removeItem('auth_token');
            delete axios.defaults.headers.common['Authorization'];
        },
    },
});
