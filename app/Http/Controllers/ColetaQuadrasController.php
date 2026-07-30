<?php

namespace App\Http\Controllers;

use App\Models\ColetaAtribuicao;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * R67-4 — GeoJSON das quadras para o mapa de atribuição de região (painel do tenant).
 * Cada quadra vem com o status de ocupação no PERÍODO informado:
 *   livre | minha (desta atribuição) | ocupada (de outro cadastrador, com o nome)
 * Assim o gestor clica no mapa e não consegue atribuir a mesma quadra duas vezes.
 */
class ColetaQuadrasController extends Controller
{
    public function __invoke(Request $request)
    {
        $tenant = Tenant::find((int) $request->query('tenant_id'));

        abort_unless($tenant, 404);

        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        abort_unless($user && $user->tenants()->whereKey($tenant->id)->exists(), 403);

        $inicio = $request->query('data_inicio') ?: now()->toDateString();
        $fim = $request->query('data_fim'); // null = sem prazo
        $ignorarId = (int) $request->query('ignorar_id'); // a própria atribuição em edição

        // Atribuições que SE SOBREPÕEM ao período informado (períodos encerrados não bloqueiam).
        // Sobreposição de [a1,a2] com [b1,b2] (fim null = sem prazo):
        //   (a2 IS NULL OR a2 >= b1) AND (b2 IS NULL OR a1 <= b2)
        $conflitantes = ColetaAtribuicao::withoutGlobalScopes()
            ->with('user:id,name')
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->whereNull('deleted_at')
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
            ->where(fn ($q) => $q->whereNull('data_fim')->orWhereDate('data_fim', '>=', $inicio))
            ->when($fim, fn ($q) => $q->whereDate('data_inicio', '<=', $fim))
            ->get(['id', 'user_id', 'quadra_ids', 'data_inicio', 'data_fim']);

        // quadra_id => quem já a possui no período (nome + id para colorir no mapa)
        $ocupadas = [];
        foreach ($conflitantes as $atribuicao) {
            foreach (($atribuicao->quadra_ids ?? []) as $quadraId) {
                $ocupadas[(int) $quadraId] = [
                    'nome' => $atribuicao->user?->name ?? 'outro cadastrador',
                    'user_id' => (int) $atribuicao->user_id,
                    'periodo' => $atribuicao->data_inicio?->format('d/m/Y')
                        .' → '.($atribuicao->data_fim?->format('d/m/Y') ?? 'sem prazo'),
                ];
            }
        }

        $quadras = DB::table('quadras')
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->whereNotNull('geo')
            ->selectRaw('id, name, ST_AsGeoJSON(geo, 6) AS geo_json,
                (SELECT COUNT(*) FROM lotes l WHERE l.quadra_id = quadras.id AND l.deleted_at IS NULL) AS total_lotes')
            ->get();

        $features = $quadras->map(fn ($q) => [
            'type' => 'Feature',
            'geometry' => json_decode($q->geo_json),
            'properties' => [
                'id' => (int) $q->id,
                'name' => $q->name,
                'total_lotes' => (int) $q->total_lotes,
                'ocupada_por' => $ocupadas[(int) $q->id]['nome'] ?? null,
                'ocupada_por_id' => $ocupadas[(int) $q->id]['user_id'] ?? null,
                'periodo' => $ocupadas[(int) $q->id]['periodo'] ?? null,
            ],
        ])->values();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }
}
