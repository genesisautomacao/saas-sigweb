<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'            => 'required|email',
            'password'         => 'required',
            'expo_push_token'  => 'nullable|string',   // ← campo opcional do mobile
        ]);

        $user = User::firstWhere('email', $request->email);

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        }

        // Atualiza o push token se o app enviou um
        if ($request->expo_push_token) {
            $user->update(['expo_push_token' => $request->expo_push_token]);
        }

        $token = $user->createToken('app-mobile')->plainTextToken;

        $tenant  = $user->tenants()->first();
        $data    = $tenant?->data ?? [];
        $modules = $tenant?->modules ?? [];

        $layerMap = [
            'arborizacao' => ['arvores'],
            'iluminacao'  => ['postes'],
            'cemiterio'   => ['cemiterios', 'jazigos'],
        ];

        $layers = ['lotes', 'quadras', 'logradouros', 'bairros'];
        foreach ($layerMap as $mod => $camadas) {
            if (in_array($mod, $modules)) {
                $layers = array_merge($layers, $camadas);
            }
        }

        return response()->json([
            'token'  => $token,
            'user'   => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
            'tenant' => [
                'id'       => $tenant?->id,
                'name'     => $tenant?->name,
                'city'     => $data['city']     ?? null,
                'state'    => $data['state']    ?? null,
                'map_lat'  => $data['map_lat']  ?? null,
                'map_lon'  => $data['map_lon']  ?? null,
                'map_zoom' => $data['map_zoom'] ?? null,
            ],
            'layers' => $layers,
        ]);
    }

    public function logout(Request $request)
    {
        /** @var \Laravel\Sanctum\PersonalAccessToken $token */
        $token = $request->user()->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Sessão encerrada com sucesso no dispositivo.'
        ]);
    }

    public function me(Request $request)
    {
        // Rota de teste para ver se o Token está funcionando
        return response()->json($request->user());
    }
}
