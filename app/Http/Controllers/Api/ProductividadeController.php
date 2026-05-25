<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductividadeController extends Controller
{
    /**
     * GET /api/reports/productivity
     * Retorna estatísticas de coleta em campo para supervisores.
     *
     * Query params (todos opcionais):
     *   - data      : YYYY-MM-DD (padrão: hoje)
     *   - quadra_id : filtrar por quadra específica
     */
    public function __invoke(Request $request)
    {
        $tenantId  = $request->user()->tenants()->first()->id;
        $data      = $request->input('data', now()->toDateString());
        $quadraId  = $request->input('quadra_id');

        $base = DB::table('lotes')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNotNull('geo');

        if ($quadraId) {
            $base->where('quadra_id', $quadraId);
        }

        // Totais gerais por status
        $totaisPorStatus = (clone $base)
            ->selectRaw("status_cadastro, COUNT(*) as total")
            ->groupBy('status_cadastro')
            ->pluck('total', 'status_cadastro');

        $totalLotes        = $totaisPorStatus->sum();
        $totalColetados    = (int) ($totaisPorStatus['coletado'] ?? 0);
        $totalPendentes    = (int) ($totaisPorStatus['pendente'] ?? 0);
        $totalInconform    = (int) ($totaisPorStatus['inconformidade'] ?? 0);
        $totalNaoVisitados = (int) ($totaisPorStatus['nao_visitado'] ?? 0);

        // Por cadastrador (totais históricos + hoje)
        $porCadastrador = DB::table('lotes as l')
            ->join('users as u', 'u.id', '=', 'l.coletado_por_id')
            ->where('l.tenant_id', $tenantId)
            ->whereNull('l.deleted_at')
            ->whereIn('l.status_cadastro', ['coletado', 'pendente', 'inconformidade'])
            ->when($quadraId, fn ($q) => $q->where('l.quadra_id', $quadraId))
            ->selectRaw("
                u.id as user_id,
                u.name as nome,
                COUNT(*) as coletados_total,
                SUM(CASE WHEN DATE(l.coletado_em) = ? THEN 1 ELSE 0 END) as coletados_hoje
            ", [$data])
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('coletados_hoje')
            ->get()
            ->map(fn ($r) => [
                'user_id'         => $r->user_id,
                'nome'            => $r->nome,
                'coletados_hoje'  => (int) $r->coletados_hoje,
                'coletados_total' => (int) $r->coletados_total,
            ]);

        // Por quadra
        $porQuadra = DB::table('lotes as l')
            ->leftJoin('quadras as q', 'q.id', '=', 'l.quadra_id')
            ->where('l.tenant_id', $tenantId)
            ->whereNull('l.deleted_at')
            ->whereNotNull('l.geo')
            ->when($quadraId, fn ($q) => $q->where('l.quadra_id', $quadraId))
            ->selectRaw("
                l.quadra_id,
                q.name as nome_quadra,
                COUNT(*) as total,
                SUM(CASE WHEN l.status_cadastro = 'coletado' THEN 1 ELSE 0 END) as coletados,
                SUM(CASE WHEN l.status_cadastro = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                SUM(CASE WHEN l.status_cadastro = 'inconformidade' THEN 1 ELSE 0 END) as inconformidades,
                SUM(CASE WHEN l.status_cadastro = 'nao_visitado' THEN 1 ELSE 0 END) as nao_visitados
            ")
            ->groupBy('l.quadra_id', 'q.name')
            ->orderByDesc('coletados')
            ->get()
            ->map(fn ($r) => [
                'quadra_id'       => $r->quadra_id,
                'nome_quadra'     => $r->nome_quadra ?? 'Sem quadra',
                'total'           => (int) $r->total,
                'coletados'       => (int) $r->coletados,
                'pendentes'       => (int) $r->pendentes,
                'inconformidades' => (int) $r->inconformidades,
                'nao_visitados'   => (int) $r->nao_visitados,
                'percentual'      => $r->total > 0 ? round(($r->coletados / $r->total) * 100, 1) : 0,
            ]);

        return response()->json([
            'data'            => $data,
            'total_lotes'     => $totalLotes,
            'coletados'       => $totalColetados,
            'pendentes'       => $totalPendentes,
            'inconformidades' => $totalInconform,
            'nao_visitados'   => $totalNaoVisitados,
            'percentual_geral'=> $totalLotes > 0 ? round(($totalColetados / $totalLotes) * 100, 1) : 0,
            'por_cadastrador' => $porCadastrador,
            'por_quadra'      => $porQuadra,
        ]);
    }
}
