<template>
    <button
        type="button"
        @click="onClick"
        :aria-label="ariaLabel"
        :aria-pressed="theme.isDark"
        :title="ariaLabel"
        class="group relative inline-flex items-center justify-center w-9 h-9 rounded-full border transition-all duration-200
               bg-slate-800/60 hover:bg-slate-700/80 border-slate-700/60 hover:border-slate-600
               text-amber-300 hover:text-amber-200
               active:scale-95
               focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900"
        :class="theme.isDark ? 'toggle-dark' : 'toggle-light'"
    >
        <span class="relative w-5 h-5 overflow-hidden">
            <!-- Sol -->
            <svg
                class="icon-sun absolute inset-0 w-5 h-5 transition-all duration-500 ease-out"
                :class="theme.isDark
                    ? 'opacity-0 -rotate-90 scale-50'
                    : 'opacity-100 rotate-0 scale-100'"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <circle cx="12" cy="12" r="4" />
                <path d="M12 2v2" />
                <path d="M12 20v2" />
                <path d="m4.93 4.93 1.41 1.41" />
                <path d="m17.66 17.66 1.41 1.41" />
                <path d="M2 12h2" />
                <path d="M20 12h2" />
                <path d="m4.93 19.07 1.41-1.41" />
                <path d="m17.66 6.34 1.41-1.41" />
            </svg>

            <!-- Luna -->
            <svg
                class="icon-moon absolute inset-0 w-5 h-5 transition-all duration-500 ease-out"
                :class="theme.isDark
                    ? 'opacity-100 rotate-0 scale-100'
                    : 'opacity-0 rotate-90 scale-50'"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
            </svg>
        </span>
    </button>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();

const ariaLabel = computed(() =>
    theme.isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro',
);

function onClick() {
    theme.toggle();
}
</script>

<style scoped>
/* En light mode el botón cambia de paleta: fondo claro, texto indigo,
   borde sutil. Tailwind 4 no genera variantes condicionales a <html class>,
   pero como los tokens slate están invertidos, las clases slate-XXX ya
   se adaptan. Sólo necesitamos cambiar el acento de ámbar → indigo. */
.toggle-light {
    background-color: rgb(241 245 249 / 0.7);
    border-color: rgb(203 213 225 / 0.8);
    color: rgb(79 70 229); /* indigo-600 */
}
.toggle-light:hover {
    background-color: rgb(226 232 240 / 0.9);
    border-color: rgb(165 180 252 / 0.8);
    color: rgb(67 56 202); /* indigo-700 */
}
.toggle-light:focus-visible {
    --tw-ring-offset-color: #f8fafc;
}
</style>
