import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useQuotaStore } from '@/stores/quota';

const routes: RouteRecordRaw[] = [
    {
        path: '/login',
        name: 'login',
        component: () => import('@/views/LoginView.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/register',
        name: 'register',
        component: () => import('@/views/RegisterView.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/study',
        name: 'study',
        component: () => import('@/views/SpecialtySelectView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/quiz',
        name: 'quiz',
        component: () => import('@/views/QuizView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/errors',
        name: 'errors',
        component: () => import('@/views/ErrorBankView.vue'),
        meta: { requiresAuth: true, requiresPremium: true },
    },
    {
        path: '/bookmarked',
        name: 'bookmarked',
        component: () => import('@/views/BookmarkedView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/flashcards',
        name: 'flashcards',
        component: () => import('@/views/FlashcardsView.vue'),
        meta: { requiresAuth: true, requiresPremium: true },
    },
    {
        path: '/admin',
        name: 'admin',
        component: () => import('@/views/AdminDashboardView.vue'),
        meta: { requiresAuth: true, role: 'admin' },
    },
    {
        path: '/admin/users',
        name: 'admin.users',
        component: () => import('@/views/AdminUsersView.vue'),
        meta: { requiresAuth: true, role: 'admin' },
    },
    {
        path: '/admin/questions',
        name: 'admin.questions',
        component: () => import('@/views/AdminQuestionsView.vue'),
        meta: { requiresAuth: true, role: 'admin' },
    },
    {
        path: '/admin/specialties',
        name: 'admin.specialties',
        component: () => import('@/views/AdminSpecialtiesView.vue'),
        meta: { requiresAuth: true, role: 'admin' },
    },
    {
        path: '/admin/subscriptions',
        name: 'admin.subscriptions',
        component: () => import('@/views/AdminSubscriptionsView.vue'),
        meta: { requiresAuth: true, role: 'admin' },
    },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: () => import('@/views/UserDashboard.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/pricing',
        name: 'pricing',
        component: () => import('@/views/PricingView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/my-subscription',
        name: 'my-subscription',
        component: () => import('@/views/MySubscriptionView.vue'),
        meta: { requiresAuth: true },
    },
    { path: '/', redirect: '/login' },
    { path: '/:pathMatch(.*)*', redirect: '/login' },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    // Si hay token en localStorage pero no tenemos user cargado, validamos contra el backend.
    // Si la validación falla, el interceptor de axios ya redirige a /login, pero acá
    // también limpiamos el estado por las dudas.
    if (auth.token && !auth.user) {
        try {
            await auth.fetchMe();
        } catch {
            auth.clear();
            if (to.meta.requiresAuth) return { name: 'login' };
        }
    }

    if (to.meta.requiresAuth && !auth.token) {
        return { name: 'login' };
    }

    if (to.meta.guestOnly && auth.token) {
        return { name: 'study' };
    }

    if (to.meta.role && auth.user) {
        const required = to.meta.role as string;
        if (!auth.user.roles.includes(required)) {
            return { name: 'study' };
        }
    }

    if (to.meta.requiresPremium) {
        const quotaStore = useQuotaStore();
        if (!quotaStore.quota) {
            try {
                await quotaStore.fetch();
            } catch {
                // ignorar
            }
        }
        if (!quotaStore.isPremium) {
            return { name: 'study', query: { upgrade: to.name as string } };
        }
    }
});

export default router;
