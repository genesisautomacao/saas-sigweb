<?php

namespace App\Services\Permissoes;

use App\Models\Tenant;
use App\Support\MapaPermissoes;
use App\Support\Modulos;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Release 83 — vocabulário único de permissões do mapa (docs/pendenciasTangara.md).
 *
 * Planeja (e aplica, pela migration) a conversão dos papéis das prefeituras com a
 * regra decidida pelo usuário em 2026-10-12, "preservar quem já cria":
 *  - gerenciar_X das 12 entidades editáveis no mapa → view/create/edit/delete_X;
 *  - ver_camada_X + toolbar_criar_artefatos → create/edit/delete_X (quem só via fica só leitura);
 *  - toolbar_ferramentas → todas as ferramenta_* do menu (numeração, medir, imprimir, SHP, CAD…);
 *  - toolbar_filtros → ferramenta_filtros + ferramenta_estatisticas;
 *  - toolbar_criar_artefatos → ferramenta_desmembrar;
 *  - cores das camadas e Street View (sem gate antes) → todos os papéis;
 *  - enquadramento → ninguém (era só Master/Manager, que têm bypass);
 *  - papel "acompanha todos os módulos" (Manager) → todas as permissões novas dos módulos ativos.
 * Ao final as permissões antigas (12 gerenciar_ + 3 toolbar_) são apagadas (cascata nos pivôs).
 * Idempotente: rodar de novo não concede nada (as antigas já não existem).
 *
 *   php artisan permissoes:simular-r83   (relatório, sem gravar)
 *   php artisan migrate                  (aplica)
 */
class MigracaoPermissoesMapaR83
{
    /** gerenciar_X antigo => entidade do catálogo config/mapa.php */
    public const GERENCIAR_PARA_ENTIDADE = [
        'gerenciar_areas_reurb' => 'areas_reurb',
        'gerenciar_secoes_logradouro' => 'secoes_logradouro',
        'gerenciar_face_quadras' => 'face_quadras',
        'gerenciar_pgv_amostras' => 'pgv_amostras',
        'gerenciar_pgv_polos' => 'pgv_polos',
        'gerenciar_mob_trechos' => 'mob_trechos',
        'gerenciar_mob_vias' => 'mob_vias',
        'gerenciar_mob_sinalizacoes' => 'mob_sinalizacoes',
        'gerenciar_mob_pontos_interesse' => 'mob_pontos_interesse',
        'gerenciar_mob_eixos' => 'mob_eixos',
        'gerenciar_mob_zonas' => 'mob_zonas',
        'gerenciar_mob_cameras' => 'mob_cameras',
    ];

    public const TOOLBAR = ['toolbar_criar_artefatos', 'toolbar_ferramentas', 'toolbar_filtros'];

    /** O que `toolbar_ferramentas` liberava (menu Ferramentas + Imprimir/Exportar + CAD). */
    public const FERRAMENTAS_DO_MENU = [
        'numeracao', 'altimetria', 'unificar', 'azimute', 'coordxy', 'sentidos', 'lidar', 'wms',
        'pgv_simulador', 'pgv_motor', 'pgv_amostra', 'pgv_polo', 'medir', 'capturar',
        'imprimir', 'exportar_shp', 'cad',
    ];

    public const FERRAMENTAS_DE_FILTROS = ['filtros', 'estatisticas'];

    public const FERRAMENTAS_DE_CRIAR = ['desmembrar'];

    /** Sem gate antes da R83 → todos os papéis continuam com elas. */
    public const FERRAMENTAS_LIVRES = ['cores', 'street_view'];

    /**
     * @return array{novas: string[], apagar: string[], papeis: array<int, array{role_id:int, tenant:string, nome:string, adicionar:string[]}>}
     */
    public function plano(): array
    {
        $existentes = DB::table('permissions')->where('guard_name', 'web')->pluck('name')->all();
        $novas = array_values(array_diff(MapaPermissoes::todasPermissoes(), $existentes));
        $apagar = array_values(array_intersect(array_merge(array_keys(self::GERENCIAR_PARA_ENTIDADE), self::TOOLBAR), $existentes));

        $tenants = DB::table('tenants')->get(['id', 'slug', 'modules'])->keyBy('id');
        $permsPorPapel = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->get(['role_has_permissions.role_id', 'permissions.name'])
            ->groupBy('role_id')
            ->map(fn ($g) => $g->pluck('name')->all());

        $papeis = [];
        $roles = DB::table('roles')->whereNotNull('tenant_id')->orderBy('tenant_id')->orderBy('name')->get();
        foreach ($roles as $role) {
            $tem = $permsPorPapel[$role->id] ?? [];
            $tenantRow = $tenants[$role->tenant_id] ?? null;
            $modules = is_string($tenantRow?->modules) ? (array) json_decode($tenantRow->modules, true) : (array) ($tenantRow?->modules ?? []);
            $tenant = new Tenant(['modules' => $modules]);

            $add = [];
            if ($role->papel_sistema === null) {
                $add = $this->concessoesDoPapelComum($tem);
            }
            if ((bool) $role->todos_modulos) {
                // Papel que acompanha todos os módulos recebe tudo que é novo nos módulos ativos
                $inativas = Modulos::permissoesInativas($tenant);
                foreach ($novas as $p) {
                    if (! in_array($p, $inativas, true)) {
                        $add[] = $p;
                    }
                }
            }

            $add = array_values(array_unique(array_diff($add, $tem)));
            if ($add !== []) {
                $papeis[] = [
                    'role_id' => (int) $role->id,
                    'tenant' => (string) ($tenantRow?->slug ?? $role->tenant_id),
                    'nome' => (string) $role->name,
                    'adicionar' => $add,
                ];
            }
        }

        return ['novas' => $novas, 'apagar' => $apagar, 'papeis' => $papeis];
    }

    /** Regra "preservar quem já cria" para um papel comum, a partir do que ele tem hoje. */
    private function concessoesDoPapelComum(array $tem): array
    {
        $add = [];
        $entidades = MapaPermissoes::entidades();

        // (a) gerenciar_X → quarteto
        foreach (self::GERENCIAR_PARA_ENTIDADE as $antiga => $entidade) {
            if (! in_array($antiga, $tem, true)) {
                continue;
            }
            foreach (['menu', 'criar', 'editar', 'excluir'] as $acao) {
                if (! empty($entidades[$entidade][$acao])) {
                    $add[] = $entidades[$entidade][$acao];
                }
            }
        }

        // (b) via a camada + podia criar artefatos → criar/editar/excluir da entidade
        if (in_array('toolbar_criar_artefatos', $tem, true)) {
            foreach ($entidades as $def) {
                if (empty($def['mapa']) || ! in_array($def['mapa'], $tem, true)) {
                    continue;
                }
                foreach (['criar', 'editar', 'excluir'] as $acao) {
                    if (! empty($def[$acao])) {
                        $add[] = $def[$acao];
                    }
                }
            }
            foreach (self::FERRAMENTAS_DE_CRIAR as $id) {
                $add[] = MapaPermissoes::permissaoFerramenta($id);
            }
        }

        // (c) e (d) toolbars → ferramentas
        if (in_array('toolbar_ferramentas', $tem, true)) {
            foreach (self::FERRAMENTAS_DO_MENU as $id) {
                $add[] = MapaPermissoes::permissaoFerramenta($id);
            }
        }
        if (in_array('toolbar_filtros', $tem, true)) {
            foreach (self::FERRAMENTAS_DE_FILTROS as $id) {
                $add[] = MapaPermissoes::permissaoFerramenta($id);
            }
        }

        // (f) o que não tinha gate
        foreach (self::FERRAMENTAS_LIVRES as $id) {
            $add[] = MapaPermissoes::permissaoFerramenta($id);
        }

        return $add;
    }

    /** Aplica o plano (migration). Devolve o plano aplicado para o relatório. */
    public function aplicar(): array
    {
        $plano = $this->plano();

        DB::transaction(function () use ($plano) {
            $agora = now();
            foreach ($plano['novas'] as $nome) {
                DB::table('permissions')->insert(['name' => $nome, 'guard_name' => 'web', 'created_at' => $agora, 'updated_at' => $agora]);
            }
            $ids = DB::table('permissions')->where('guard_name', 'web')->pluck('id', 'name');

            foreach ($plano['papeis'] as $papel) {
                $linhas = [];
                foreach ($papel['adicionar'] as $nome) {
                    if (isset($ids[$nome])) {
                        $linhas[] = ['role_id' => $papel['role_id'], 'permission_id' => $ids[$nome]];
                    }
                }
                if ($linhas !== []) {
                    DB::table('role_has_permissions')->insertOrIgnore($linhas);
                }
            }

            if ($plano['apagar'] !== []) {
                // FK dos pivôs (role_has_permissions / model_has_permissions) é ON DELETE CASCADE
                DB::table('permissions')->where('guard_name', 'web')->whereIn('name', $plano['apagar'])->delete();
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $plano;
    }

    /**
     * Reverte (down da migration), no melhor esforço: recria as antigas e as concede a
     * quem tem o equivalente novo; apaga o que a R83 criou.
     */
    public function reverter(): void
    {
        DB::transaction(function () {
            $agora = now();
            $antigas = array_merge(array_keys(self::GERENCIAR_PARA_ENTIDADE), self::TOOLBAR);
            foreach ($antigas as $nome) {
                if (! DB::table('permissions')->where('guard_name', 'web')->where('name', $nome)->exists()) {
                    DB::table('permissions')->insert(['name' => $nome, 'guard_name' => 'web', 'created_at' => $agora, 'updated_at' => $agora]);
                }
            }
            $ids = DB::table('permissions')->where('guard_name', 'web')->pluck('id', 'name');
            $entidades = MapaPermissoes::entidades();

            $permsPorPapel = DB::table('role_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->get(['role_has_permissions.role_id', 'permissions.name'])
                ->groupBy('role_id')
                ->map(fn ($g) => $g->pluck('name')->all());

            foreach ($permsPorPapel as $roleId => $tem) {
                $add = [];
                foreach (self::GERENCIAR_PARA_ENTIDADE as $antiga => $entidade) {
                    $def = $entidades[$entidade] ?? null;
                    if ($def && ! empty($def['editar']) && in_array($def['editar'], $tem, true)) {
                        $add[] = $antiga;
                    }
                }
                foreach ($entidades as $def) {
                    if (! empty($def['mapa']) && ! empty($def['criar']) && in_array($def['criar'], $tem, true)) {
                        $add[] = 'toolbar_criar_artefatos';
                        break;
                    }
                }
                foreach (self::FERRAMENTAS_DO_MENU as $id) {
                    if (in_array(MapaPermissoes::permissaoFerramenta($id), $tem, true)) {
                        $add[] = 'toolbar_ferramentas';
                        break;
                    }
                }
                foreach (self::FERRAMENTAS_DE_FILTROS as $id) {
                    if (in_array(MapaPermissoes::permissaoFerramenta($id), $tem, true)) {
                        $add[] = 'toolbar_filtros';
                        break;
                    }
                }
                $linhas = [];
                foreach (array_unique($add) as $nome) {
                    $linhas[] = ['role_id' => $roleId, 'permission_id' => $ids[$nome]];
                }
                if ($linhas !== []) {
                    DB::table('role_has_permissions')->insertOrIgnore($linhas);
                }
            }

            // Apaga o que a R83 criou: quarteto das 12 convertidas, toponímias e ferramenta_*
            $criadas = [];
            foreach (self::GERENCIAR_PARA_ENTIDADE as $entidade) {
                foreach (['menu', 'criar', 'editar', 'excluir'] as $acao) {
                    if (! empty($entidades[$entidade][$acao])) {
                        $criadas[] = $entidades[$entidade][$acao];
                    }
                }
            }
            $criadas = array_merge($criadas, ['create_toponimias', 'edit_toponimias', 'delete_toponimias']);
            foreach (array_keys(MapaPermissoes::ferramentas()) as $id) {
                $criadas[] = MapaPermissoes::permissaoFerramenta($id);
            }
            DB::table('permissions')->where('guard_name', 'web')->whereIn('name', $criadas)->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
