<?php

namespace App\Services\Coleta;

use App\Models\ColetaAtribuicao;
use Illuminate\Support\Facades\DB;

/**
 * R67-4 — região de trabalho do cadastrador (quadras/bairros por período).
 * Define o recorte de lotes que o app baixa: sem atribuição vigente o cadastrador
 * não baixa nada (evita carregar a base inteira e travar o app).
 */
class ColetaRegiaoService
{
    /**
     * Quadras que o usuário pode ver HOJE:
     * - null  → SEM RESTRIÇÃO (Master/Manager: supervisão/testes baixam tudo);
     * - []    → sem atribuição vigente → NENHUM lote;
     * - array → ids das quadras atribuídas (diretas + as dos bairros atribuídos).
     */
    public static function quadrasPermitidas(int $tenantId, int $userId): ?array
    {
        if (self::ehSupervisor($tenantId, $userId)) {
            return null;
        }

        $atribuicoes = ColetaAtribuicao::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->vigentes()
            ->get(['quadra_ids', 'bairro_ids']);

        if ($atribuicoes->isEmpty()) {
            return [];
        }

        $quadras = $atribuicoes->flatMap(fn ($a) => $a->quadra_ids ?? [])->all();
        $bairros = $atribuicoes->flatMap(fn ($a) => $a->bairro_ids ?? [])->all();

        if (! empty($bairros)) {
            // Molde do filtro por bairro do mapa: lote → quadra → bairro
            $quadras = array_merge($quadras, DB::table('quadras')
                ->where('tenant_id', $tenantId)
                ->whereIn('bairro_id', $bairros)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->all());
        }

        return array_values(array_unique(array_map('intval', $quadras)));
    }

    /**
     * Resumo da região para o app ("você está trabalhando em...").
     * O campo `modo` é EXPLÍCITO para o app não precisar interpretar null/vazio:
     *   livre           → supervisor: baixa toda a base
     *   restrita        → cadastrador com região: baixa só as quadras listadas
     *   sem_atribuicao  → nenhuma região hoje: não baixa nada (avisar o usuário)
     */
    public static function resumoRegiao(int $tenantId, int $userId): array
    {
        $quadraIds = self::quadrasPermitidas($tenantId, $userId);

        if ($quadraIds === null) {
            return ['modo' => 'livre', 'quadra_ids' => [], 'atribuicoes' => []];
        }

        if (empty($quadraIds)) {
            return ['modo' => 'sem_atribuicao', 'quadra_ids' => [], 'atribuicoes' => []];
        }

        $atribuicoes = ColetaAtribuicao::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->vigentes()
            ->get();

        $nomeQuadras = DB::table('quadras')->whereIn('id', $quadraIds)->pluck('name', 'id');

        return [
            'modo' => 'restrita',
            'quadra_ids' => $quadraIds,
            'atribuicoes' => $atribuicoes->map(fn (ColetaAtribuicao $a) => [
                'data_inicio' => $a->data_inicio?->toDateString(),
                'data_fim' => $a->data_fim?->toDateString(),
                'quadras' => collect($a->quadra_ids ?? [])
                    ->map(fn ($id) => ['id' => (int) $id, 'name' => $nomeQuadras[$id] ?? null])
                    ->values()->all(),
                'bairros' => DB::table('bairros')
                    ->whereIn('id', $a->bairro_ids ?? [])
                    ->get(['id', 'name'])
                    ->map(fn ($b) => ['id' => (int) $b->id, 'name' => $b->name])
                    ->all(),
            ])->values()->all(),
        ];
    }

    /**
     * Master/Manager do tenant ignoram a exigência de atribuição.
     * Query raw no molde do Gate::before do AppServiceProvider (evita cache do Spatie).
     */
    protected static function ehSupervisor(int $tenantId, int $userId): bool
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $userId)
            ->where('model_has_roles.model_type', \App\Models\User::class)
            ->where('roles.tenant_id', $tenantId)
            ->whereIn('roles.name', ['Master', 'Manager'])
            ->exists();
    }
}
