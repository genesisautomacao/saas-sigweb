<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CadastradorLocationController extends Controller
{
    /**
     * POST /api/cadastradores/location
     * O app mobile posta a posição GPS a cada ~60s (upsert por usuário).
     */
    public function store(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lon' => 'required|numeric|between:-180,180',
        ]);

        $tenantId = $request->user()->tenants()->first()->id;
        $userId   = $request->user()->id;

        DB::table('cadastrador_locations')->upsert(
            [
                'user_id'   => $userId,
                'tenant_id' => $tenantId,
                'lat'       => (float) $request->input('lat'),
                'lon'       => (float) $request->input('lon'),
                'updated_at'=> now(),
            ],
            ['user_id'],
            ['lat', 'lon', 'updated_at']
        );

        return response()->json(['ok' => true]);
    }

    /**
     * GET /api/cadastradores/locations
     * O supervisor WEB busca a posição de todos os cadastradores ativos do tenant.
     * "Ativo" = posição atualizada nos últimos 10 minutos.
     */
    public function index(Request $request)
    {
        $tenantId = $request->user()->tenants()->first()->id;

        $locations = DB::table('cadastrador_locations as cl')
            ->join('users as u', 'u.id', '=', 'cl.user_id')
            ->where('cl.tenant_id', $tenantId)
            ->where('cl.updated_at', '>=', now()->subMinutes(10))
            ->selectRaw('
                u.id as user_id,
                u.name as nome,
                cl.lat,
                cl.lon,
                cl.updated_at as ultima_atualizacao
            ')
            ->get();

        // Adiciona contagem de lotes coletados hoje por cadastrador
        $hoje = now()->toDateString();
        $coletadosHoje = DB::table('lotes')
            ->where('tenant_id', $tenantId)
            ->whereDate('coletado_em', $hoje)
            ->whereIn('coletado_por_id', $locations->pluck('user_id'))
            ->groupBy('coletado_por_id')
            ->selectRaw('coletado_por_id, COUNT(*) as total')
            ->pluck('total', 'coletado_por_id');

        $result = $locations->map(fn ($loc) => [
            'user_id'            => $loc->user_id,
            'nome'               => $loc->nome,
            'lat'                => (float) $loc->lat,
            'lon'                => (float) $loc->lon,
            'ultima_atualizacao' => $loc->ultima_atualizacao,
            'coletados_hoje'     => (int) ($coletadosHoje[$loc->user_id] ?? 0),
        ]);

        return response()->json(['cadastradores' => $result]);
    }
}
