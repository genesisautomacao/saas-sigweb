<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\BuildsMobileAuthResponse;
use App\Http\Controllers\Controller;
use App\Models\Pessoa;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Autenticação do CIDADÃO no app de Chamados (Módulo XVI).
 *
 * Três portas de entrada exigidas pelo edital, todas devolvendo o MESMO payload
 * (via BuildsMobileAuthResponse) que o login por e-mail/senha do AuthController:
 *   - e-mail/senha  → register() (cadastro) + AuthController::login (login)
 *   - Google        → google()   (recebe o id_token do app)
 *   - Facebook      → facebook()  (recebe o access_token do app)
 *
 * O cidadão é sempre criado como User `tipo=cidadao` (sem papel), vinculado a UMA
 * prefeitura (pivot tenant_user) e a uma Pessoa no tenant — espelhando o fluxo web
 * de RegisterCidadao. Endpoints públicos (sem auth:sanctum).
 */
class CidadaoAuthController extends Controller
{
    use BuildsMobileAuthResponse;

    /** GET /api/prefeituras — lista pública para o app oferecer a seleção de cidade. */
    public function prefeituras()
    {
        $tenants = Tenant::orderBy('name')->get()->map(fn (Tenant $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'slug' => $t->slug,
            'city' => data_get($t->data, 'city'),
            'state' => data_get($t->data, 'state'),
        ]);

        return response()->json(['data' => $tenants]);
    }

    /** POST /api/cidadao/register — cadastro por e-mail/senha (espelha o RegisterCidadao do web). */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'cpf' => 'nullable|string|max:14',
            'telefone' => 'nullable|string|max:20',
            'tenant_id' => 'required_without:prefeitura_slug|integer',
            'prefeitura_slug' => 'required_without:tenant_id|string',
            'expo_push_token' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tenant = $this->resolverTenant($request);
        if (! $tenant) {
            return response()->json(['message' => 'Prefeitura não encontrada.'], 404);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password, // o cast 'hashed' do model faz o hash uma vez
            'tipo' => 'cidadao',
        ]);

        $user->tenants()->attach($tenant->id);
        $this->vincularPessoa($user, $tenant->id, $request->name, $request->cpf, $request->telefone);

        if ($request->expo_push_token) {
            $user->update(['expo_push_token' => $request->expo_push_token]);
        }

        return response()->json($this->mobileAuthResponse($user), 201);
    }

    /** POST /api/auth/google — o app envia o id_token do Google; validamos e logamos/cadastramos. */
    public function google(Request $request)
    {
        if ($fail = $this->validarSocial($request)) {
            return $fail;
        }

        $resp = Http::acceptJson()->get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $request->token,
        ]);

        if (! $resp->ok() || ! $resp->json('email')) {
            return response()->json(['message' => 'Token do Google inválido.'], 401);
        }

        // Se um client_id do Google estiver configurado, exige que o token seja deste app.
        $expectedAud = config('services.google.client_id');
        if ($expectedAud && $resp->json('aud') !== $expectedAud) {
            return response()->json(['message' => 'Token do Google não pertence a este aplicativo.'], 401);
        }

        return $this->loginSocial($request, $resp->json('email'), $resp->json('name') ?: 'Cidadão');
    }

    /** POST /api/auth/facebook — o app envia o access_token do Facebook; validamos e logamos/cadastramos. */
    public function facebook(Request $request)
    {
        if ($fail = $this->validarSocial($request)) {
            return $fail;
        }

        $resp = Http::acceptJson()->get('https://graph.facebook.com/me', [
            'fields' => 'id,name,email',
            'access_token' => $request->token,
        ]);

        if (! $resp->ok() || ! $resp->json('id')) {
            return response()->json(['message' => 'Token do Facebook inválido.'], 401);
        }

        // O Facebook pode não devolver e-mail (conta sem e-mail ou permissão negada):
        // criamos um e-mail sintético estável a partir do ID para manter a conta única.
        $email = $resp->json('email') ?: ('fb_'.$resp->json('id').'@facebook.local');

        return $this->loginSocial($request, $email, $resp->json('name') ?: 'Cidadão');
    }

    /** Validação comum dos endpoints sociais (token + prefeitura). Retorna a resposta 422 ou null. */
    private function validarSocial(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'tenant_id' => 'required_without:prefeitura_slug|integer',
            'prefeitura_slug' => 'required_without:tenant_id|string',
            'expo_push_token' => 'nullable|string',
        ]);

        return $validator->fails()
            ? response()->json(['errors' => $validator->errors()], 422)
            : null;
    }

    /** Encontra ou cria o cidadão a partir dos dados sociais e devolve o payload de auth. */
    private function loginSocial(Request $request, string $email, string $name)
    {
        $tenant = $this->resolverTenant($request);
        if (! $tenant) {
            return response()->json(['message' => 'Prefeitura não encontrada.'], 404);
        }

        $user = User::firstWhere('email', $email);

        if (! $user) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Str::random(40), // aleatória; a autenticação é pelo provedor social
                'tipo' => 'cidadao',
                'email_verified_at' => now(),
            ]);
        }

        // Garante o vínculo com a prefeitura escolhida (idempotente) + Pessoa no tenant.
        if (! $user->tenants()->whereKey($tenant->id)->exists()) {
            $user->tenants()->attach($tenant->id);
        }
        $this->vincularPessoa($user, $tenant->id, $name, null, null);

        if ($request->expo_push_token) {
            $user->update(['expo_push_token' => $request->expo_push_token]);
        }

        return response()->json($this->mobileAuthResponse($user));
    }

    private function resolverTenant(Request $request): ?Tenant
    {
        if ($request->filled('tenant_id')) {
            return Tenant::find($request->tenant_id);
        }
        if ($request->filled('prefeitura_slug')) {
            return Tenant::where('slug', $request->prefeitura_slug)->first();
        }

        return null;
    }

    /**
     * Cria/vincula a Pessoa do cidadão no tenant (dedup por CPF quando houver, senão por user_id).
     * tenant_id explícito porque a API roda fora do contexto de tenant do Filament.
     */
    private function vincularPessoa(User $user, int $tenantId, string $name, ?string $cpf, ?string $telefone): void
    {
        $base = fn () => Pessoa::withoutGlobalScopes()->where('tenant_id', $tenantId);

        $pessoa = $cpf ? $base()->where('cpf', $cpf)->first() : null;
        $pessoa ??= $base()->where('user_id', $user->id)->first();

        if ($pessoa) {
            if (empty($pessoa->user_id)) {
                $pessoa->user_id = $user->id;
            }
            if (empty($pessoa->telefone) && $telefone) {
                $pessoa->telefone = $telefone;
            }
            $pessoa->save();

            return;
        }

        $pessoa = new Pessoa;
        $pessoa->tenant_id = $tenantId;
        $pessoa->user_id = $user->id;
        $pessoa->name = $name;
        $pessoa->cpf = $cpf;
        $pessoa->telefone = $telefone;
        $pessoa->type = 'fisica';
        $pessoa->save();
    }
}
