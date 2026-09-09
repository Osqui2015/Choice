<?php

use Illuminate\Support\Facades\Route;

// Catch-all SPA: cualquier ruta que NO sea /api/* devuelve la app Vue.
// Vue Router maneja el routing del lado del cliente.
Route::get('/{any?}', fn () => view('app'))->where('any', '^(?!api).*$');
