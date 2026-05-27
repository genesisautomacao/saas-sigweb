<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mensagem;
use App\Models\User;
use App\Services\Expo\ExpoPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MensagemController extends Controller
{
    /**
     * GET /api/contatos
     * Lista usuários do mesmo tenant que tenham AO MENOS uma role atribuída
     * (exclui cidadãos do portal sem permissões de funcionário) — exceto o próprio.
     */
    public function contatos(Request $request)
    {
        $tenantId = $request->user()->tenants()->first()?->id;
        if (!$tenantId) {
            return response()->json(['error' => 'Usuário sem tenant ativo.'], 403);
        }

        $userId = $request->user()->id;

        $contatos = DB::table('users as u')
            ->join('tenant_user as tu', 'tu.user_id', '=', 'u.id')
            ->leftJoin('model_has_roles as mhr', function ($j) use ($tenantId) {
                $j->on('mhr.model_id', '=', 'u.id')
                    ->where('mhr.model_type', User::class)
                    ->where('mhr.tenant_id', $tenantId);
            })
            ->leftJoin('roles as r', 'r.id', '=', 'mhr.role_id')
            ->where('tu.tenant_id', $tenantId)
            ->where('u.id', '!=', $userId)
            ->whereNotNull('mhr.role_id')
            ->select('u.id', 'u.name', 'u.email', DB::raw('MIN(r.name) as role'))
            ->groupBy('u.id', 'u.name', 'u.email')
            ->orderBy('u.name')
            ->get();

        return response()->json(['data' => $contatos]);
    }

    /**
     * GET /api/mensagens
     * Lista mensagens onde o usuário logado é remetente OU destinatário.
     * Filtros opcionais: ?contato_id=X (filtra conversa específica).
     */
    public function index(Request $request)
    {
        $tenantId = $request->user()->tenants()->first()?->id;
        if (!$tenantId) {
            return response()->json(['error' => 'Usuário sem tenant ativo.'], 403);
        }

        $userId = $request->user()->id;
        $contatoId = $request->query('contato_id');

        $query = Mensagem::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($userId) {
                $q->where('remetente_id', $userId)
                  ->orWhere('destinatario_id', $userId);
            });

        if ($contatoId) {
            $query->where(function ($q) use ($userId, $contatoId) {
                $q->where(function ($q2) use ($userId, $contatoId) {
                    $q2->where('remetente_id', $userId)
                       ->where('destinatario_id', $contatoId);
                })->orWhere(function ($q2) use ($userId, $contatoId) {
                    $q2->where('remetente_id', $contatoId)
                       ->where('destinatario_id', $userId);
                });
            });
        }

        $mensagens = $query->orderBy('created_at', 'desc')
            ->take(200)
            ->get();

        return response()->json(['data' => $mensagens]);
    }

    /**
     * POST /api/mensagens
     * Body: {destinatario_id, texto}
     */
    public function store(Request $request)
    {
        $tenantId = $request->user()->tenants()->first()?->id;
        if (!$tenantId) {
            return response()->json(['error' => 'Usuário sem tenant ativo.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'destinatario_id' => 'required|integer|exists:users,id',
            'texto'           => 'required|string|min:1|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validar que destinatário pertence ao mesmo tenant
        $destinatario = User::query()->find($request->input('destinatario_id'));
        $destinatarioPertenceAoTenant = $destinatario
            ->tenants()
            ->where('tenants.id', $tenantId)
            ->exists();

        if (!$destinatarioPertenceAoTenant) {
            return response()->json([
                'errors' => ['destinatario_id' => ['Destinatário não pertence ao seu tenant.']]
            ], 422);
        }

        $mensagem = Mensagem::create([
            'tenant_id'       => $tenantId,
            'remetente_id'    => $request->user()->id,
            'destinatario_id' => (int) $request->input('destinatario_id'),
            'texto'           => $request->input('texto'),
        ]);

        // Push notification (best-effort — falhas não interrompem o fluxo)
        if ($destinatario->expo_push_token) {
            app(ExpoPushService::class)->send(
                expoToken: $destinatario->expo_push_token,
                title: $request->user()->name,
                body: mb_strimwidth($request->input('texto'), 0, 100, '...'),
                data: [
                    'tipo'       => 'mensagem',
                    'contatoId'  => $request->user()->id,
                    'mensagemId' => $mensagem->id,
                ],
            );
        }

        return response()->json(['data' => $mensagem], 201);
    }

    /**
     * PUT /api/mensagens/{id}/lida
     * Marca a mensagem como lida — só permitido se o usuário logado é o destinatário.
     */
    public function marcarLida(Request $request, int $id)
    {
        $tenantId = $request->user()->tenants()->first()?->id;
        if (!$tenantId) {
            return response()->json(['error' => 'Usuário sem tenant ativo.'], 403);
        }

        $mensagem = Mensagem::query()
            ->where('tenant_id', $tenantId)
            ->find($id);

        if (!$mensagem) {
            return response()->json(['error' => 'Mensagem não encontrada.'], 404);
        }

        if ($mensagem->destinatario_id !== $request->user()->id) {
            return response()->json([
                'error' => 'Apenas o destinatário pode marcar como lida.'
            ], 403);
        }

        if (is_null($mensagem->lido_em)) {
            $mensagem->update(['lido_em' => now()]);
        }

        return response()->json(['data' => $mensagem]);
    }
}
