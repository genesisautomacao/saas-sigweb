<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\BuildsMobileAuthResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
        // Perfil do usuário logado. Inclui telefone/cpf da Pessoa do tenant ativo
        // (usado pelo app para preencher a tela "Editar perfil" — item 187).
        $user = $request->user();
        $tenantId = $user->tenants()->first()?->id;
        $pessoa = $tenantId ? $user->pessoaNoTenant($tenantId) : null;

        $data = $user->toArray();
        $data['telefone'] = $pessoa?->telefone;
        $data['cpf'] = $pessoa?->cpf;
        $data['data_nascimento'] = $this->fmtDate($pessoa?->birth_date);

        return response()->json($data);
    }

    /** Normaliza uma data (Carbon ou string) para 'Y-m-d', ou null. */
    private function fmtDate($valor): ?string
    {
        return $valor ? \Illuminate\Support\Carbon::parse($valor)->toDateString() : null;
    }

    /**
     * PUT /api/me — edita o perfil do usuário logado (item 187).
     * Atualiza User (name/email/senha) e a Pessoa do tenant ativo (telefone/cpf).
     * Só altera os campos enviados. Troca de senha exige `current_password` correta.
     */
    public function updateMe(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'telefone' => 'sometimes|nullable|string|max:20',
            'cpf' => 'sometimes|nullable|string|max:14',
            'data_nascimento' => 'sometimes|nullable|date',
            'current_password' => 'required_with:password|string',
            'password' => 'sometimes|string|min:6',
            'expo_push_token' => 'sometimes|nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Troca de senha exige a senha atual correta.
        if ($request->filled('password')) {
            if (! Hash::check($request->input('current_password'), $user->password)) {
                return response()->json(['errors' => ['current_password' => ['Senha atual incorreta.']]], 422);
            }
            $user->password = $request->input('password'); // cast 'hashed' faz o hash
        }

        if ($request->filled('name')) {
            $user->name = $request->input('name');
        }
        if ($request->filled('email')) {
            $user->email = $request->input('email');
        }
        if ($request->filled('expo_push_token')) {
            $user->expo_push_token = $request->input('expo_push_token');
        }
        $user->save();

        // Reflete no cadastro de Pessoa do cidadão no tenant ativo.
        $tenantId = $user->tenants()->first()?->id;
        $pessoa = $tenantId ? $user->pessoaNoTenant($tenantId) : null;
        if ($pessoa) {
            if ($request->filled('name')) {
                $pessoa->name = $request->input('name');
            }
            if ($request->has('telefone')) {
                $pessoa->telefone = $request->input('telefone');
            }
            if ($request->has('cpf')) {
                $pessoa->cpf = $request->input('cpf');
            }
            if ($request->has('data_nascimento')) {
                $pessoa->birth_date = $request->input('data_nascimento');
            }
            $pessoa->save();
        }

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'telefone' => $pessoa?->telefone,
            'cpf' => $pessoa?->cpf,
            'data_nascimento' => $this->fmtDate($pessoa?->birth_date),
        ]]);
    }
}
