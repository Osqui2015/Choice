<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta id="theme-color-meta" name="theme-color" content="#0f172a">

        <title>{{ config('app.name', 'Choice') }}</title>

        {{-- PWA / iOS --}}
        <link rel="manifest" href="{{ asset('build/manifest.webmanifest') }}">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('icons/icon-512.png') }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Choice">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="description" content="Banco de preguntas y flashcards de medicina para cátedras y residencias">

        {{--
            Anti-FOUC: este script se ejecuta de forma SÍNCRONA antes de que
            Vite monte nada. Aplica la clase .dark al <html> según la
            preferencia guardada o la del sistema operativo. Sin esto, los
            usuarios en light mode verían un flash oscuro de ~100-300ms.
        --}}
        <script>
            (function () {
                try {
                    var stored = localStorage.getItem('choice_theme');
                    var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                    var theme = stored || (prefersDark ? 'dark' : 'light');
                    var root = document.documentElement;
                    if (theme === 'dark') {
                        root.classList.add('dark');
                    } else {
                        root.classList.remove('dark');
                    }
                    root.classList.add('theme-ready');
                    var meta = document.getElementById('theme-color-meta');
                    if (meta) {
                        meta.setAttribute('content', theme === 'dark' ? '#0f172a' : '#f8fafc');
                    }
                    // Exponer para que el store de Pinia lo recoja en mount
                    window.__CHOICE_THEME__ = theme;
                } catch (e) {
                    // Silenciar cualquier error (modo privado, etc.)
                    document.documentElement.classList.add('dark', 'theme-ready');
                }
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
    </head>
    <body class="min-h-screen antialiased selection:bg-indigo-500 selection:text-white">
        <div id="app"></div>
    </body>
</html>
