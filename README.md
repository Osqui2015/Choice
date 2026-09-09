# Choice — Plataforma de Estudio Médico

Plataforma web + PWA para práctica intensiva de preguntas tipo *multiple choice* orientadas a estudiantes de medicina (Cátedras y Residencias). Inspirada en UWorld y AMBOSS, con modelo de acceso por planes y pago por WhatsApp.

## Stack

- **Backend**: Laravel 12 (PHP 8.2+), MySQL, Laravel Sanctum (SPA/PWA auth)
- **Frontend**: Vue 3 (Composition API + `<script setup>`) + Vite 7 + TypeScript
- **Estado**: Pinia (`useAuthStore`, `useQuizStore`, `useQuotaStore`, `useSubscriptionStore`)
- **Estilos**: Tailwind CSS v4
- **PWA**: `vite-plugin-pwa` (Service Worker, offline, instalable)
- **Roles/Permisos**: Spatie Laravel Permission

## Setup local

```bash
# 1. Dependencias
composer install
npm install

# 2. Configurar entorno
cp .env.example .env
php artisan key:generate

# 3. Base de datos (MySQL)
#    Crear DB "choice_app" en MySQL primero
php artisan migrate
php artisan db:seed --class=PlanSeeder

# 4. Importar banco de preguntas
php artisan questions:import

# 5. Levantar
php artisan serve          # backend en :8000
npm run dev                # frontend con HMR
```

## Modelo de planes

| Plan | Materias | Precio (ARS) |
|---|---|---|
| 🆓 **Gratuito** | 1 materia, 5 preguntas/día | $0 |
| 📚 **Básico** | 1 materia ilimitada + flashcards | $4.990 / mes |
| 🎯 **Estudiante** | 3 materias ilimitadas + flashcards | $9.990 / mes |
| 🏆 **Full** | Todas las materias + flashcards | $14.990 / mes |

**Pago por WhatsApp**: el user solicita un plan → el backend genera un link `wa.me/{número}?text=...` con los datos pre-armados → vos confirmás el pago y activás el plan desde `/admin/subscriptions`.

**Configurar tu número**: editar `WHATSAPP_PREMIUM_NUMBER` en `.env`.

## Rutas clave

### User
- `GET /api/plans` — catálogo público de planes
- `GET /api/me/subscription` — estado de suscripción + cuota free
- `POST /api/me/subscription-requests` — solicitar plan (devuelve link WhatsApp)
- `POST /api/me/choose-free-specialty` — elegir materia free

### Admin (`/admin/*`, requiere rol `admin`)
- `GET /api/admin/subscription-requests?status=pending` — solicitudes pendientes
- `POST /api/admin/subscription-requests/{id}/approve` — aprobar
- `POST /api/admin/subscription-requests/{id}/reject` — rechazar (con motivo)
- `POST /api/admin/users/{user}/subscriptions` — activar plan manualmente

**Nota**: a los usuarios con rol `admin` se les activa automáticamente un plan Full vitalicio (`payment_provider: admin_grant`). El bypass está en `SubscriptionService::checkSpecialtyAccess()`.

## Contenido del banco

- **3.230 preguntas MCQ** activas (Neumonología, Cardiología, Nefrología)
- **328 flashcards clínicas**
- **48 temas** · **16 secciones**
- Importadas desde `MCQ_CLNICA_1.xlsx` vía `php artisan questions:import`
- Estado de import persistente en `storage/app/import_state.json`

## Estructura destacada

```
app/
├── Http/
│   ├── Controllers/Api/
│   │   ├── AuthController.php
│   │   ├── QuizController.php          # legacy, con middleware specialty.access
│   │   ├── PlanController.php          # NUEVO
│   │   ├── SubscriptionController.php  # NUEVO
│   │   └── AdminController.php
│   └── Middleware/
│       ├── EnsureSpecialtyAccess.php   # NUEVO (valida acceso por plan)
│       └── ForceJsonResponse.php
├── Models/
│   ├── Plan.php                        # NUEVO
│   ├── UserSubscription.php            # NUEVO
│   ├── SubscriptionSpecialty.php       # NUEVO
│   ├── Question.php
│   ├── Specialty.php
│   └── ...
├── Observers/
│   └── UserObserver.php                # NUEVO (auto-plan Full para admins)
├── Providers/
│   └── AdminAutoPlanProvider.php       # NUEVO
└── Services/
    ├── SubscriptionService.php         # NUEVO (core del sistema de planes)
    ├── QuotaService.php                # legacy
    └── StreakService.php

resources/js/
├── views/
│   ├── PricingView.vue                 # NUEVO
│   ├── MySubscriptionView.vue          # NUEVO
│   ├── AdminSubscriptionsView.vue      # NUEVO
│   └── ...
├── components/
│   ├── PlanAcquireModal.vue            # NUEVO
│   ├── FreeSpecialtyModal.vue          # NUEVO
│   └── PricingModal.vue
└── stores/
    └── subscription.ts                 # NUEVO (Pinia store)
```

## Licencia

Privado. Todos los derechos reservados.
