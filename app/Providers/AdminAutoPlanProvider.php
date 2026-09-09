<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Support\ServiceProvider;

class AdminAutoPlanProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Registramos el observer para que cuando se asigne el rol admin,
        // automáticamente se active un plan Full.
        // Spatie dispara eventos RoleAttached y RoleDetached.
        User::observe([UserObserver::class]);

        // Hook directo a los eventos de Spatie para que el observer escuche
        // cuando se asigna un rol (RoleAttached event).
        \Event::listen(\Spatie\Permission\Events\RoleAttached::class, function ($event) {
            /** @var \Spatie\Permission\Events\RoleAttached $event */
            $user = $event->model ?? null;
            $role = $event->role ?? null;
            if ($user && $role && method_exists($user, 'hasRole')) {
                $roleName = is_object($role) ? $role->name : (string) $role;
                app(UserObserver::class)->roleAttached($user, $roleName);
            }
        });
    }
}
