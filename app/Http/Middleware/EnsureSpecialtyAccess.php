<?php

namespace App\Http\Middleware;

use App\Models\Specialty;
use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de acceso por especialidad.
 *
 * Uso en rutas:
 *   Route::middleware('specialty.access:questions')->...
 *   Route::middleware('specialty.access:flashcards')->...
 *
 * Resuelve el specialty_id desde:
 *   - ?specialty_id= en query string
 *   - specialty_id en body JSON
 *   - parámetro de ruta {specialty}
 *
 * Si el user no tiene acceso:
 *   - 402 con payload explicando el motivo + catálogo de planes.
 */
class EnsureSpecialtyAccess
{
    public function __construct(protected SubscriptionService $service) {}

    public function handle(Request $request, Closure $next, string $context = 'questions'): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $specialtyId = $this->resolveSpecialtyId($request);

        // Si no hay specialty_id en la request, no bloqueamos.
        // La responsabilidad de filtrar por plan queda en el controller.
        // Esto permite que /api/flashcards?limit=30 funcione para usuarios
        // que quieren ver todas sus flashcards (o todas para admins).
        if (! $specialtyId) {
            return $next($request);
        }

        $specialty = Specialty::find($specialtyId);
        if (! $specialty) {
            return response()->json(['message' => 'Especialidad no encontrada.'], 404);
        }

        $check = $this->service->checkSpecialtyAccess($user, $specialtyId);

        if (! $check['allowed']) {
            return response()->json([
                'message'          => 'No tenés acceso a esta materia con tu plan actual.',
                'reason'           => $check['reason'],
                'specialty'        => [
                    'id'   => $specialty->id,
                    'name' => $specialty->name,
                    'code' => $specialty->code,
                ],
                'context'          => $context,
                'reset_at'         => $check['reset_at'] ?? null,
                'seconds_to_reset' => $check['seconds_to_reset'] ?? null,
                'free_limit'       => $check['free_limit'] ?? SubscriptionService::FREE_DAILY_LIMIT,
                'plans'            => \App\Models\Plan::where('is_active', true)
                    ->orderBy('sort_order')
                    ->get(['id', 'name', 'slug', 'price', 'currency', 'duration_days', 'max_specialties', 'includes_flashcards']),
            ], 402);
        }

        // Dejar info de acceso en el request para que el controller la use
        $request->attributes->set('access_check', $check);
        $request->attributes->set('specialty_id', $specialtyId);

        return $next($request);
    }

    protected function resolveSpecialtyId(Request $request): ?int
    {
        // 1) Parámetro de ruta
        $routeParam = $request->route('specialty');
        if ($routeParam instanceof Specialty) {
            return (int) $routeParam->id;
        }
        if (is_numeric($routeParam)) {
            return (int) $routeParam;
        }

        // 2) Query string
        $query = $request->query('specialty_id');
        if (is_numeric($query)) {
            return (int) $query;
        }

        // 3) Body JSON
        $body = $request->json()->all();
        if (isset($body['specialty_id']) && is_numeric($body['specialty_id'])) {
            return (int) $body['specialty_id'];
        }

        return null;
    }
}
