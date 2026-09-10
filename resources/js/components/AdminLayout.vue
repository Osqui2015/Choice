<template>
    <div class="min-h-screen bg-slate-900 text-slate-100 flex flex-col">
        <Navbar />
        <div class="flex-1 flex">
            <!-- Sidebar -->
            <aside class="hidden sm:block w-56 bg-slate-800/40 border-r border-slate-800 p-3">
                <nav class="space-y-1 sticky top-20">
                    <router-link
                        v-for="item in items"
                        :key="item.to"
                        :to="item.to"
                        class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-slate-300 hover:bg-slate-800 hover:text-slate-100"
                        active-class="bg-indigo-600/20 text-white border border-indigo-500/30"
                    >
                        <span>{{ item.icon }}</span>
                        <span>{{ item.label }}</span>
                    </router-link>
                </nav>
            </aside>

            <main class="flex-1 max-w-6xl w-full mx-auto px-4 sm:px-6 py-6">
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup lang="ts">
import Navbar from '@/components/Navbar.vue';

const items = [
    { to: '/admin', label: 'Dashboard', icon: '📊' },
    { to: '/admin/users', label: 'Usuarios', icon: '👥' },
    { to: '/admin/questions', label: 'Preguntas', icon: '❓' },
    { to: '/admin/specialties', label: 'Especialidades', icon: '📚' },
];
</script>
