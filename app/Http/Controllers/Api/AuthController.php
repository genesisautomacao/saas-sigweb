<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\BuildsMobileAuthResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use BuildsMobileAuthResponse;

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'expo_push_token' => 'nullable|string',   // ← campo opcional do mobile
        ]);

        $user = User::firstWhere('email', $request->email);

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        }

        // Atualiza o push token se o app enviou um
        if ($request->expo_push_token) {
            $user->update(['expo_push_token' => $request->expo_push_token]);
        }

        return response()->json($this->mobileAuthResponse($user));
    }

    public function logout(Request $request)
    {
        /** @var \Laravel\Sanctum\PersonalAccessToken $token */
        $token = $request->user()->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Sessão encerrada com sucesso no dispositivo.',
        ]);
    }

    public function me(Request $request)
    {
        // Rota de teste para ver se o Token está funcionando
        return response()->json($request->user());
    }
}
