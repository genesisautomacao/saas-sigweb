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

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        }

        // Atualiza o push token se o app enviou um
        if ($request->expo_push_token) {
            $user->update(['expo_push_token' => $request->expo_push_token]);
        }

        $token = $user->createToken('app-mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'        => $user->id,
                'name'      => $user->name,
                'email'     => $user->email,
                'tenant_id' => $user->tenants()->first()->id ?? null,
            ]
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
