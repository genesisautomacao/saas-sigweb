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
        // 1. Valida se o celular mandou os dados certos
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        // 2. Confere a senha
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Credenciais inválidas.'
            ], 401);
        }

        // 3. A MÁGICA: Gera o crachá de acesso para o celular
        $token = $user->createToken('app-mobile')->plainTextToken;

        // 4. Devolve o Token e os dados do usuário (incluindo o Tenant!)
        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                // Pega o ID da primeira prefeitura que este usuário tem acesso
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