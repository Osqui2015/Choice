import { defineStore } from 'pinia';
import { computed, ref } from 'vue';

export type Theme = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'choice_theme';

declare global {
    interface Window {
        __CHOICE_THEME__?: 'light' | 'dark';
    }
}

/**
 * Devuelve la preferencia inicial: lo que haya escrito el script anti-FOUC en
 * `window.__CHOICE_THEME__` (o sea: lo que el usuario ya tenía aplicado al
 * pintar la página) y, en su defecto, 'dark' por compatibilidad con la
 * versión previa de la app que era 100% dark.
 */
function resolveInitial(): Theme {
    if (typeof window === 'undefined') return 'dark';
    const stored = localStorage.getItem(STORAGE_KEY) as Theme | null;
    if (stored === 'light' || stored === 'dark' || stored === 'system') {
        return stored;
    }
    return 'dark';
}

function systemPrefersDark(): boolean {
    return typeof window !== 'undefined'
        && window.matchMedia
        && window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function effectiveIsDark(theme: Theme): boolean {
    return theme === 'dark' || (theme === 'system' && systemPrefersDark());
}

function applyToDom(isDark: boolean): void {
    if (typeof document === 'undefined') return;
    const root = document.documentElement;
    if (isDark) {
        root.classList.add('dark');
    } else {
        root.classList.remove('dark');
    }
    // Sincronizar el theme-color de la barra del navegador / PWA
    const meta = document.getElementById('theme-color-meta') as HTMLMetaElement | null;
    if (meta) {
        meta.setAttribute('content', isDark ? '#0f172a' : '#f8fafc');
    }
}

export const useThemeStore = defineStore('theme', () => {
    const theme = ref<Theme>(resolveInitial());
    const isDark = computed(() => effectiveIsDark(theme.value));

    let mediaQuery: MediaQueryList | null = null;
    let mediaListener: ((e: MediaQueryListEvent) => void) | null = null;

    function persist(): void {
        try {
            localStorage.setItem(STORAGE_KEY, theme.value);
        } catch {
            // modo privado / storage lleno — ignorar
        }
    }

    function apply(): void {
        applyToDom(isDark.value);
    }

    function setTheme(next: Theme): void {
        theme.value = next;
        persist();
        apply();
    }

    /** Cicla entre dark y light. Si la preferencia es 'system', el primer
     *  click fija el opuesto al modo actual del sistema. */
    function toggle(): void {
        const next: Theme = isDark.value ? 'light' : 'dark';
        setTheme(next);
    }

    function onSystemChange(e: MediaQueryListEvent): void {
        if (theme.value === 'system') {
            applyToDom(e.matches);
        }
    }

    function init(): void {
        apply();
        if (typeof window === 'undefined') return;
        if (!mediaQuery) {
            mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            mediaListener = onSystemChange;
            // addEventListener es la API moderna; addListener es la legacy
            if (mediaQuery.addEventListener) {
                mediaQuery.addEventListener('change', mediaListener);
            } else if ((mediaQuery as any).addListener) {
                (mediaQuery as any).addListener(mediaListener);
            }
        }
    }

    function dispose(): void {
        if (mediaQuery && mediaListener) {
            if (mediaQuery.removeEventListener) {
                mediaQuery.removeEventListener('change', mediaListener);
            } else if ((mediaQuery as any).removeListener) {
                (mediaQuery as any).removeListener(mediaListener);
            }
            mediaQuery = null;
            mediaListener = null;
        }
    }

    return {
        theme,
        isDark,
        setTheme,
        toggle,
        init,
        dispose,
    };
});
