<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Specialty;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    /**
     * GET /api/plans
     * Catálogo público de planes activos.
     */
    public function index(): JsonResponse
    {
        $plans = Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function (Plan $plan) {
                return [
                    'id'                  => $plan->id,
                    'name'                => $plan->name,
                    'slug'                => $plan->slug,
                    'description'         => $plan->description,
                    'price'               => $plan->price,
                    'currency'            => $plan->currency,
                    'formatted_price'     => $plan->formattedPrice(),
                    'duration_days'       => $plan->duration_days,
                    'max_specialties'     => $plan->max_specialties, // null = ilimitadas
                    'is_unlimited'        => $plan->isUnlimited(),
                    'includes_flashcards' => $plan->includes_flashcards,
                ];
            });

        $specialties = Specialty::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'code', 'name', 'slug', 'icon', 'color']);

        return response()->json([
            'plans'       => $plans,
            'specialties' => $specialties,
        ]);
    }
}
