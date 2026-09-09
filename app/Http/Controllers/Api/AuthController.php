<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Registro autónomo de un estudiante.
     * Crea el usuario con rol 'usuario', inicializa su cuota diaria y devuelve token.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'phone'    => ['required', 'string', 'min:8', 'max:32', 'regex:/^[+0-9\s\-()]+$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'accepts_promotions' => ['nullable', 'boolean'],
        ], [
            'phone.required' => 'Necesitamos tu WhatsApp para coordinar pagos y avisos.',
            'phone.regex'    => 'Formato de teléfono inválido. Usá solo números, +, espacios o guiones.',
        ]);

        $user = User::create([
            'name'                => $data['name'],
            'email'               => $data['email'],
            'phone'               => $this->normalizePhone($data['phone']),
            'accepts_promotions'  => (bool) ($data['accepts_promotions'] ?? false),
            'password'            => $data['password'], // 'hashed' cast
        ]);
        $user->assignRole('usuario');

        // Inicializa la cuota diaria
        \App\Models\UserDailyQuota::firstOrCreate(
            ['user_id' => $user->id],
            ['daily_limit' => 10, 'questions_answered_today' => 0]
        );

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'roles' => $user->getRoleNames(),
            ],
        ], 201);
    }

    /**
     * Normaliza el teléfono a solo dígitos con código de país (sin +, sin espacios).
     * Ej: "+54 9 11 1234-5678" → "5491112345678"
     */
    protected function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }

    /**
     * Login: valida credenciales y devuelve un token Sanctum + el usuario con su rol.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales son incorrectas.'],
            ]);
        }

        // Revocamos tokens previos para que cada login devuelva uno limpio.
        $user->tokens()->delete();
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
            ],
        ]);
    }

    /**
     * Devuelve el usuario autenticado + sus roles.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'roles' => $user->getRoleNames(),
        ]);
    }

    /**
     * Cierra sesión revocando el token actual.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada']);
    }
}
