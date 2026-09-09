<template>
    <AdminLayout>
        <div class="space-y-4">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <h1 class="text-2xl font-bold text-white">👥 Usuarios</h1>
                <button
                    @click="openCreate"
                    class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold"
                >+ Nuevo usuario</button>
            </div>

            <!-- Filtros -->
            <div class="grid sm:grid-cols-4 gap-2">
                <input
                    v-model="filters.search"
                    @input="debouncedLoad"
                    type="text"
                    placeholder="Buscar por nombre o email…"
                    class="px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-sm text-white placeholder-slate-500"
                />
                <select v-model="filters.role" @change="load(1)" class="px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-sm text-white">
                    <option value="">Todos los roles</option>
                    <option value="admin">Admin</option>
                    <option value="usuario">Usuario</option>
                </select>
                <select v-model="filters.is_premium" @change="load(1)" class="px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-sm text-white">
                    <option value="">Todos</option>
                    <option value="true">Premium</option>
                    <option value="false">Free</option>
                </select>
                <select v-model="filters.is_active" @change="load(1)" class="px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-sm text-white">
                    <option value="">Todos</option>
                    <option value="true">Activos</option>
                    <option value="false">Inactivos</option>
                </select>
            </div>

            <!-- Tabla -->
            <div class="bg-slate-800 border border-slate-700 rounded-2xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-900/60 text-xs text-slate-400 uppercase">
                        <tr>
                            <th class="px-3 py-2 text-left">Usuario</th>
                            <th class="px-3 py-2 text-left">Rol</th>
                            <th class="px-3 py-2 text-left">Premium</th>
                            <th class="px-3 py-2 text-left">Activo</th>
                            <th class="px-3 py-2 text-left">Creado</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading"><td colspan="6" class="px-3 py-8 text-center text-slate-500">Cargando…</td></tr>
                        <tr v-else-if="!users.length"><td colspan="6" class="px-3 py-8 text-center text-slate-500">No hay usuarios con esos filtros.</td></tr>
                        <tr v-for="u in users" v-else :key="u.id" class="border-t border-slate-700/60">
                            <td class="px-3 py-2.5">
                                <p class="font-semibold text-white">{{ u.name }}</p>
                                <p class="text-xs text-slate-400">{{ u.email }}</p>
                            </td>
                            <td class="px-3 py-2.5">
                                <span v-for="r in u.roles" :key="r" class="px-2 py-0.5 rounded-full bg-indigo-500/20 border border-indigo-500/30 text-indigo-300 text-xs font-semibold">{{ r }}</span>
                            </td>
                            <td class="px-3 py-2.5">
                                <button
                                    @click="togglePremium(u)"
                                    :class="['px-2 py-1 rounded text-xs font-semibold', u.is_premium ? 'bg-yellow-500/20 text-yellow-300' : 'bg-slate-700 text-slate-400']"
                                >{{ u.is_premium ? '⚡ Premium' : 'Free' }}</button>
                            </td>
                            <td class="px-3 py-2.5">
                                <button
                                    @click="toggleActive(u)"
                                    :class="['px-2 py-1 rounded text-xs font-semibold', u.is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-red-500/20 text-red-300']"
                                >{{ u.is_active ? 'Activo' : 'Inactivo' }}</button>
                            </td>
                            <td class="px-3 py-2.5 text-xs text-slate-400">{{ formatDate(u.created_at) }}</td>
                            <td class="px-3 py-2.5 text-right">
                                <button @click="openEdit(u)" class="text-indigo-300 hover:text-indigo-200 text-xs font-semibold">Editar</button>
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
        </div>

        <!-- Modal crear/editar -->
        <Teleport to="body">
            <div v-if="editing" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4" @click.self="editing = null">
                <div class="bg-slate-800 border border-slate-700 rounded-2xl max-w-md w-full p-6">
                    <h2 class="text-xl font-bold text-white mb-4">{{ editing.id ? 'Editar usuario' : 'Nuevo usuario' }}</h2>
                    <form @submit.prevent="save" class="space-y-3">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Nombre</label>
                            <input v-model="editing.name" type="text" required class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-white" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Email</label>
                            <input v-model="editing.email" type="email" required class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-white" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Contraseña {{ editing.id ? '(dejar vacío para no cambiar)' : '' }}</label>
                            <input v-model="editing.password" type="password" :required="!editing.id" minlength="8" class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-white" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Rol</label>
                            <select v-model="editing.role" required class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-white">
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
                        <div class="flex gap-2 pt-2">
                            <button type="button" @click="editing = null" class="flex-1 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-white text-sm">Cancelar</button>
                            <button type="submit" :disabled="saving" class="flex-1 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold disabled:opacity-50">{{ saving ? 'Guardando…' : 'Guardar' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import AdminLayout from '@/components/AdminLayout.vue';

interface UserRow {
    id: number;
    name: string;
    email: string;
    roles: string[];
    is_premium: boolean;
    is_active: boolean;
    premium_until: string | null;
    created_at: string;
}

const users = ref<UserRow[]>([]);
const loading = ref(false);
const saving = ref(false);
const meta = reactive({ current_page: 1, last_page: 1, per_page: 20, total: 0 });
const filters = reactive({ search: '', role: '', is_premium: '', is_active: '' });
const editing = ref<any>(null);

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
        const { data } = await axios.get('/admin/users', { params });
        users.value = data.data;
        Object.assign(meta, data.meta);
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    editing.value = { name: '', email: '', password: '', role: 'usuario', is_premium: false, is_active: true };
}

function openEdit(u: UserRow) {
    editing.value = { ...u, password: '' };
}

async function save() {
    if (!editing.value) return;
    saving.value = true;
    try {
        if (editing.value.id) {
            const payload: any = { ...editing.value };
            if (!payload.password) delete payload.password;
            await axios.patch(`/admin/users/${editing.value.id}`, payload);
        } else {
            await axios.post('/admin/users', editing.value);
        }
        editing.value = null;
        await load(meta.current_page);
    } catch (e: any) {
        alert(e.response?.data?.message || 'Error al guardar');
    } finally {
        saving.value = false;
    }
}

async function togglePremium(u: UserRow) {
    await axios.patch(`/admin/users/${u.id}`, { is_premium: !u.is_premium });
    u.is_premium = !u.is_premium;
}

async function toggleActive(u: UserRow) {
    await axios.patch(`/admin/users/${u.id}`, { is_active: !u.is_active });
    u.is_active = !u.is_active;
}

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

onMounted(load);
</script>
