<!DOCTYPE html>
<html lang="es" class="dark bg-slate-900">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0f172a">

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

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
    </head>
    <body class="bg-slate-900 text-slate-100 min-h-screen antialiased selection:bg-indigo-500 selection:text-white">
        <div id="app"></div>
    </body>
</html>
