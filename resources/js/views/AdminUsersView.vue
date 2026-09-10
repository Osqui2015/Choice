<template>
    <AdminLayout title="Usuarios">
        <div class="flex items-center justify-between mb-4">
            <p class="text-slate-400 text-sm">{{ meta.total }} usuario{{ meta.total === 1 ? '' : 's' }} en total</p>
            <button @click="openCreate" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold">+ Nuevo usuario</button>
        </div>

        <!-- Filtros -->
        <div class="grid sm:grid-cols-5 gap-3 mb-4">
            <input v-model="filters.search" @input="debouncedLoad" type="text" placeholder="Buscar por nombre, email o WhatsApp…" class="px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-sm" />
            <select v-model="filters.role" @change="load(1)" class="px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-sm">
                <option value="">Todos los roles</option>
                <option value="admin">Admin</option>
                <option value="usuario">Usuario</option>
            </select>
            <select v-model="filters.is_premium" @change="load(1)" class="px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-sm">
                <option value="">Todos</option>
                <option value="true">Premium</option>
                <option value="false">Free</option>
            </select>
            <select v-model="filters.is_active" @change="load(1)" class="px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-sm">
                <option value="">Todos</option>
                <option value="true">Activos</option>
                <option value="false">Inactivos</option>
            </select>
            <select v-model="filters.subscription_status" @change="load(1)" class="px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-sm">
                <option value="">Todos los planes</option>
                <option value="active">✓ Con plan activo</option>
                <option value="pending">⏳ Pendiente</option>
                <option value="expired">⌛ Expirado</option>
                <option value="cancelled">✕ Cancelado</option>
                <option value="free">Sin plan (Free)</option>
            </select>
        </div>

        <!-- Tabla -->
        <div class="bg-slate-800/40 border border-slate-700 rounded-2xl overflow-hidden mb-4">
            <table class="w-full text-sm">
                <thead class="bg-slate-900/60 text-xs text-slate-400 uppercase">
                    <tr>
                        <th class="px-3 py-2 text-left">Usuario</th>
                        <th class="px-3 py-2 text-left">WhatsApp</th>
                        <th class="px-3 py-2 text-left">Rol</th>
                        <th class="px-3 py-2 text-left">Plan</th>
                        <th class="px-3 py-2 text-left">Activo</th>
                        <th class="px-3 py-2 text-left">Creado</th>
                        <th class="px-3 py-2 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading"><td colspan="7" class="px-3 py-8 text-center text-slate-500">Cargando…</td></tr>
                    <tr v-else-if="!users.length"><td colspan="7" class="px-3 py-8 text-center text-slate-500">No hay usuarios con esos filtros.</td></tr>
                    <tr v-for="u in users" v-else :key="u.id" class="border-t border-slate-700/60 hover:bg-slate-800/30">
                        <td class="px-3 py-2.5">
                            <p class="font-semibold text-slate-100 flex items-center gap-1.5">
                                {{ u.name }}
                                <span v-if="u.id === auth.user?.id" class="text-[10px] px-1.5 py-0.5 rounded bg-indigo-500/20 border border-indigo-500/30 text-indigo-300 font-bold">VOS</span>
                            </p>
                            <p class="text-xs text-slate-400">{{ u.email }}</p>
                        </td>
                        <td class="px-3 py-2.5">
                            <p v-if="u.phone" class="text-sm text-slate-100 font-mono">{{ formatPhone(u.phone) }}</p>
                            <p v-else class="text-xs text-slate-500">—</p>
                            <p v-if="u.accepts_promotions" class="text-[10px] text-emerald-400">📣 Acepta promos</p>
                        </td>
                        <td class="px-3 py-2.5">
                            <span v-for="r in u.roles" :key="r" class="px-2 py-0.5 rounded-full bg-indigo-500/20 border border-indigo-500/30 text-indigo-300 text-xs font-semibold">{{ r }}</span>
                        </td>
                        <td class="px-3 py-2.5">
                            <SubscriptionCell
                                :user="u"
                                :busy-renew="busyAction === `renew-${u.id}`"
                                :busy-cancel="busyAction === `cancel-${u.id}`"
                                @renew="onRenew"
                                @cancel="onCancelSub"
                                @history="onShowHistory"
                            />
                        </td>
                        <td class="px-3 py-2.5">
                            <button
                                @click="toggleActive(u)"
                                :title="u.is_active ? 'Desactivar usuario' : 'Activar usuario'"
                                :class="[
                                    'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200',
                                    u.is_active ? 'bg-emerald-500' : 'bg-slate-600',
                                    busyId === u.id ? 'opacity-50 cursor-wait' : '',
                                ]"
                            >
                                <span
                                    :class="[
                                        'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200',
                                        u.is_active ? 'translate-x-5' : 'translate-x-0',
                                    ]"
                                />
                            </button>
                        </td>
                        <td class="px-3 py-2.5 text-xs text-slate-400">{{ formatDate(u.created_at) }}</td>
                        <td class="px-3 py-2.5 text-right">
                            <div class="inline-flex items-center gap-3">
                                <button @click="openEdit(u)" class="text-indigo-300 hover:text-indigo-200 text-xs font-semibold">Editar</button>
                                <button
                                    @click="confirmDelete(u)"
                                    :disabled="u.id === auth.user?.id"
                                    :title="u.id === auth.user?.id ? 'No podés eliminarte a vos mismo' : 'Eliminar definitivamente'"
                                    class="text-xs font-semibold text-rose-300 hover:text-rose-200 disabled:opacity-40 disabled:cursor-not-allowed"
                                >Eliminar</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <div v-if="meta.last_page > 1" class="flex items-center justify-center gap-2 text-sm">
            <button
                v-for="p in meta.last_page"
                :key="p"
                @click="load(p)"
                :class="['px-3 py-1 rounded', p === meta.current_page ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-slate-300']"
            >{{ p }}</button>
        </div>

        <!-- Modal crear/editar -->
        <Teleport to="body">
            <div v-if="editing" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4" @click.self="editing = null">
                <div class="bg-slate-800 border border-slate-700 rounded-2xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto">
                    <h2 class="text-xl font-bold text-slate-100 mb-4">{{ editing.id ? 'Editar usuario' : 'Nuevo usuario' }}</h2>
                    <form @submit.prevent="save" class="space-y-3">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Nombre completo</label>
                            <input v-model="editing.name" type="text" required class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-slate-100" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Email</label>
                            <input v-model="editing.email" type="email" required class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-slate-100" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">WhatsApp <span class="text-slate-500">(con código de país)</span></label>
                            <input v-model="editing.phone" type="tel" inputmode="tel" placeholder="+54 9 11 1234-5678" class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-slate-100" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Contraseña {{ editing.id ? '(dejar vacío para no cambiar)' : '' }}</label>
                            <input v-model="editing.password" type="password" :required="!editing.id" minlength="8" class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-slate-100" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Rol</label>
                            <select v-model="editing.role" required class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-slate-100">
                                <option value="usuario">Usuario</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex items-center gap-2 text-sm text-slate-200">
                                <input v-model="editing.is_premium" type="checkbox" class="w-4 h-4" />
                                Premium
                            </label>
                            <label class="flex items-center gap-2 text-sm text-slate-200">
                                <input v-model="editing.is_active" type="checkbox" class="w-4 h-4" />
                                Activo
                            </label>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-slate-200 pt-1">
                            <input v-model="editing.accepts_promotions" type="checkbox" class="w-4 h-4" />
                            Acepta promociones por WhatsApp
                        </label>
                        <div class="flex gap-2 pt-2">
                            <button type="button" @click="editing = null" class="flex-1 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-100 text-sm">Cancelar</button>
                            <button type="submit" :disabled="saving" class="flex-1 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold disabled:opacity-50">{{ saving ? 'Guardando…' : 'Guardar' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Modal de confirmación para eliminar -->
        <Teleport to="body">
            <div v-if="confirmingDelete" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4" @click.self="confirmingDelete = null">
                <div class="bg-slate-800 border border-slate-700 rounded-2xl max-w-md w-full p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="text-2xl">⚠️</span>
                        <h2 class="text-xl font-bold text-slate-100">Eliminar usuario</h2>
                    </div>
                    <p class="text-sm text-slate-300 mb-4">
                        Estás a punto de <strong class="text-rose-300">eliminar definitivamente</strong> a:
                    </p>
                    <div class="bg-slate-900/60 border border-slate-700 rounded-lg p-3 mb-4">
                        <p class="font-semibold text-slate-100">{{ confirmingDelete.name }}</p>
                        <p class="text-xs text-slate-400">{{ confirmingDelete.email }}</p>
                    </div>
                    <ul class="text-xs text-slate-400 space-y-1 mb-5 list-disc list-inside">
                        <li>Se borrará el usuario y todos sus datos asociados (respuestas, progreso, bookmarks, suscripciones).</li>
                        <li>Esta acción <strong class="text-rose-300">no se puede deshacer</strong>.</li>
                        <li v-if="confirmingDelete.id === auth.user?.id" class="text-amber-300 font-semibold">
                            ⚠ Estás eliminándote a vos mismo — vas a ser deslogueado.
                        </li>
                    </ul>
                    <p class="text-sm text-slate-300 mb-2">
                        Para confirmar, escribí <code class="px-1.5 py-0.5 rounded bg-slate-900 text-rose-300 font-mono text-xs">{{ confirmText }}</code>:
                    </p>
                    <input
                        v-model="deleteConfirmInput"
                        type="text"
                        :placeholder="confirmText"
                        class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-slate-100 text-sm mb-4 font-mono"
                        @keyup.enter="performDelete"
                    />
                    <div class="flex gap-2">
                        <button type="button" @click="confirmingDelete = null" class="flex-1 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-100 text-sm font-semibold">Cancelar</button>
                        <button
                            type="button"
                            @click="performDelete"
                            :disabled="deleteConfirmInput !== confirmText || deleting"
                            class="flex-1 py-2 rounded-lg bg-rose-600 hover:bg-rose-500 disabled:opacity-40 disabled:cursor-not-allowed text-white text-sm font-semibold"
                        >{{ deleting ? 'Eliminando…' : 'Eliminar definitivamente' }}</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal historial de suscripciones -->
        <SubscriptionHistoryModal
            :open="historyModal.open"
            :user="historyModal.user"
            @close="historyModal.open = false"
        />
    </AdminLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import AdminLayout from '@/components/AdminLayout.vue';
import SubscriptionCell from '@/components/SubscriptionCell.vue';
import SubscriptionHistoryModal from '@/components/SubscriptionHistoryModal.vue';
import { useAuthStore } from '@/stores/auth';

interface PlanInfo {
    id: number;
    name: string;
    slug: string;
    duration_days: number;
    is_unlimited: boolean;
    includes_flashcards: boolean;
    price: number;
    currency: string;
}

interface SubInfo {
    id: number;
    status: 'active' | 'pending' | 'cancelled' | 'expired';
    plan: PlanInfo | null;
    started_at: string | null;
    expires_at: string | null;
    days_remaining: number;
    days_total: number | null;
    progress_pct: number;
    payment_provider: string | null;
    amount_paid: number | null;
    currency: string;
    cancelled_at: string | null;
    notes: string | null;
    specialties: Array<{ id: number; name: string; code: string }>;
}

interface UserRow {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    accepts_promotions: boolean;
    roles: string[];
    is_premium: boolean;
    is_active: boolean;
    premium_until: string | null;
    created_at: string;
    active_subscription: SubInfo | null;
    latest_subscription: SubInfo | null;
}

const auth = useAuthStore();

const users = ref<UserRow[]>([]);
const loading = ref(false);
const saving = ref(false);
const busyId = ref<number | null>(null); // para que el switch no parpadee durante la request
const busyAction = ref<string | null>(null); // 'renew-{id}' | 'cancel-{id}' | null
const meta = reactive({ current_page: 1, last_page: 1, per_page: 20, total: 0 });
const filters = reactive({ search: '', role: '', is_premium: '', is_active: '', subscription_status: '' });
const editing = ref<any>(null);

// Modal de eliminación
const confirmingDelete = ref<UserRow | null>(null);
const deleteConfirmInput = ref('');
const deleting = ref(false);
const confirmText = computed(() => confirmingDelete.value?.email ?? '');

// Modal historial
const historyModal = reactive({ open: false, user: null as UserRow | null });

let searchTimer: number | null = null;
function debouncedLoad() {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => load(1), 300);
}

async function load(page = 1) {
    loading.value = true;
    try {
        const params: Record<string, any> = { per_page: 20, page };
        if (filters.search) params.search = filters.search;
        if (filters.role) params.role = filters.role;
        if (filters.is_premium !== '') params.is_premium = filters.is_premium;
        if (filters.is_active !== '') params.is_active = filters.is_active;
        if (filters.subscription_status) params.subscription_status = filters.subscription_status;
        const { data } = await axios.get('/admin/users', { params });
        users.value = data.data;
        Object.assign(meta, data.meta);
    } catch (e: any) {
        showError(e, 'Error al cargar usuarios');
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    editing.value = {
        name: '',
        email: '',
        phone: '',
        password: '',
        role: 'usuario',
        is_premium: false,
        is_active: true,
        accepts_promotions: false,
    };
}

function openEdit(u: UserRow) {
    editing.value = { ...u, password: '' };
}

async function save() {
    if (!editing.value) return;
    saving.value = true;
    try {
        const payload: any = { ...editing.value };
        if (editing.value.id && !payload.password) {
            delete payload.password;
        }
        if (Array.isArray(payload.roles)) {
            payload.role = payload.roles[0] ?? 'usuario';
            delete payload.roles;
        }
        if (payload.premium_until === '') {
            payload.premium_until = null;
        }
        if (editing.value.id) {
            await axios.patch(`/admin/users/${editing.value.id}`, payload);
        } else {
            await axios.post('/admin/users', payload);
        }
        editing.value = null;
        await load(meta.current_page);
    } catch (e: any) {
        showError(e, 'Error al guardar');
    } finally {
        saving.value = false;
    }
}

async function toggleActive(u: UserRow) {
    // Si el usuario es admin y solo queda él, no permitir desactivar
    const isAdmin = u.roles.includes('admin');

    // Advertencia si se está desactivando a sí mismo
    if (u.id === auth.user?.id && u.is_active) {
        const ok = confirm(
            '⚠ Vas a desactivarte a vos mismo.\n\n' +
            'Tu sesión va a cerrarse inmediatamente y no vas a poder ' +
            'iniciar sesión otra vez hasta que otro admin te reactive.\n\n' +
            '¿Continuar?',
        );
        if (!ok) return;
    }

    busyId.value = u.id;
    const previous = u.is_active;
    const next = !u.is_active;
    u.is_active = next;
    try {
        const { data } = await axios.patch(`/admin/users/${u.id}`, { is_active: next });
        // Si la operación nos afectó a nosotros mismos, forzar logout
        if (data?.self_affected) {
            await auth.logout();
            window.location.href = '/login';
            return;
        }
    } catch (e: any) {
        u.is_active = previous; // rollback
        showError(e, isAdmin ? 'No se puede desactivar al último admin' : 'No se pudo cambiar el estado');
    } finally {
        busyId.value = null;
    }
}

function confirmDelete(u: UserRow) {
    if (u.id === auth.user?.id) return; // no se puede eliminar a sí mismo
    deleteConfirmInput.value = '';
    confirmingDelete.value = u;
}

async function performDelete() {
    if (!confirmingDelete.value) return;
    if (deleteConfirmInput.value !== confirmText.value) return;
    deleting.value = true;
    const target = confirmingDelete.value;
    try {
        const { data } = await axios.delete(`/admin/users/${target.id}/force`);
        confirmingDelete.value = null;
        deleteConfirmInput.value = '';
        await load(meta.current_page);
        if (data?.self_affected) {
            // Por si el backend lo permitiera en algún caso futuro
            await auth.logout();
            window.location.href = '/login';
        }
    } catch (e: any) {
        showError(e, 'No se pudo eliminar el usuario');
    } finally {
        deleting.value = false;
    }
}

function showError(e: any, fallback: string) {
    const msg = e.response?.data?.message;
    const errs = e.response?.data?.errors;
    const firstFieldError = errs ? Object.values(errs).flat()[0] : null;
    alert(msg || firstFieldError || fallback);
}

async function onRenew(u: UserRow) {
    if (!u.active_subscription) return;
    const plan = u.active_subscription.plan;
    const dur = plan?.duration_days ?? 30;
    if (!confirm(`¿Renovar la suscripción de ${u.name}?\n\nSe extenderá la fecha de vencimiento +${dur} días.`)) {
        return;
    }
    busyAction.value = `renew-${u.id}`;
    try {
        await axios.post(`/admin/users/${u.id}/subscriptions/${u.active_subscription.id}/renew`);
        await load(meta.current_page);
    } catch (e: any) {
        showError(e, 'No se pudo renovar la suscripción');
    } finally {
        busyAction.value = null;
    }
}

async function onCancelSub(u: UserRow) {
    if (!u.active_subscription) return;
    if (!confirm(
        `¿Cancelar la suscripción de ${u.name}?\n\n` +
        `El usuario será deslogueado inmediatamente y perderá el acceso premium.`,
    )) {
        return;
    }
    busyAction.value = `cancel-${u.id}`;
    try {
        await axios.post(`/admin/users/${u.id}/subscriptions/${u.active_subscription.id}/cancel`);
        await load(meta.current_page);
    } catch (e: any) {
        showError(e, 'No se pudo cancelar la suscripción');
    } finally {
        busyAction.value = null;
    }
}

function onShowHistory(u: UserRow) {
    historyModal.user = u;
    historyModal.open = true;
}

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

/** Formatea "5491112345678" → "+54 9 11 1234-5678" para mostrar. */
function formatPhone(raw: string | null): string {
    if (! raw) return '';
    if (! raw.startsWith('54')) return raw;
    const rest = raw.slice(2);
    if (rest.length === 10) {
        return `+54 ${rest[0]} ${rest.slice(1, 3)} ${rest.slice(3, 7)}-${rest.slice(7)}`;
    }
    return `+54 ${rest}`;
}

onMounted(load);
</script>
