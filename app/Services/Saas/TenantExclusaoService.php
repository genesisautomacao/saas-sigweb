<?php

namespace App\Services\Saas;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Exclusão FÍSICA e definitiva de uma prefeitura (tenant) — 2026-08-27.
 *
 * O grosso do trabalho quem faz é o PostgreSQL: ~97 FKs de tenant_id têm
 * cascadeOnDelete, então apagar a linha de `tenants` derruba lotes, bairros,
 * pessoas, processos etc. em cascata. Este service cobre o que a cascata NÃO
 * alcança:
 *   1. varredura genérica pós-cascata (tabelas com tenant_id SEM FK — ex.:
 *      viabilidades — e qualquer tabela futura, descobertas via information_schema);
 *   2. usuários: exclui DE VERDADE (hard) os exclusivos da prefeitura;
 *      quem tem papel GLOBAL (Master/Operador) ou vínculo com OUTRA prefeitura
 *      é apenas desvinculado — a cascata do pivot já cuida disso;
 *   3. arquivos: fotos/anexos coletados ANTES do delete (os caminhos vivem nas
 *      linhas que a cascata apaga), logo, mock tributário, nuvem de pontos
 *      (Potree) e o prefixo {slug}/ no bucket R2 (best-effort).
 *
 * ⚠️ IRREVERSÍVEL. Sem lixeira. O dump prévio (phpPgAdmin) é responsabilidade
 * de quem executa — a ação no admin exige digitar o slug para confirmar.
 */
class TenantExclusaoService
{
    /** Nomes de coluna que guardam caminho de arquivo no disk 'public'. */
    private const COLUNAS_ARQUIVO = [
        'path', 'image_path', 'arquivo', 'caminho_arquivo', 'icone',
        'foto', 'foto_frontal', 'foto_lateral_esq', 'foto_lateral_dir',
        'foto_antes', 'foto_depois', 'foto_ocorrencia',
    ];

    /** Colunas JSON com ARRAY de caminhos (ex.: chamados.fotos). */
    private const COLUNAS_ARQUIVO_JSON = ['fotos'];

    /**
     * Tabelas REAIS (não views) com coluna tenant_id — via information_schema,
     * então tabela nova entra sozinha na varredura e na prévia de impacto.
     */
    public static function tabelasComTenantId(): array
    {
        return DB::table('information_schema.columns as c')
            ->join('information_schema.tables as t', function ($join) {
                $join->on('t.table_name', '=', 'c.table_name')
                    ->on('t.table_schema', '=', 'c.table_schema');
            })
            ->where('c.table_schema', 'public')
            ->where('c.column_name', 'tenant_id')
            ->where('t.table_type', 'BASE TABLE')
            ->where('c.table_name', '!=', 'tenants')
            ->orderBy('c.table_name')
            ->pluck('c.table_name')
            ->all();
    }

    /**
     * Prévia de impacto: contagem por tabela + usuários + arquivos.
     * Alimenta o modal de confirmação da ação no admin.
     */
    public static function previa(Tenant $tenant): array
    {
        $contagens = [];
        foreach (self::tabelasComTenantId() as $tabela) {
            $n = DB::table($tabela)->where('tenant_id', $tenant->id)->count();
            if ($n > 0) {
                $contagens[$tabela] = $n;
            }
        }
        arsort($contagens);

        [$excluir, $desvincular] = self::usuariosDoTenant($tenant->id);

        return [
            'contagens' => $contagens,
            'total_linhas' => array_sum($contagens),
            'usuarios_excluir' => count($excluir),
            'usuarios_desvincular' => count($desvincular),
            'arquivos' => count(self::coletarArquivos($tenant)),
        ];
    }

    /**
     * Separa os usuários vinculados ao tenant em [excluir, apenas desvincular].
     *
     * ⚠️ PROTEÇÕES (não remover): quem tem papel GLOBAL (roles.tenant_id IS NULL
     * — Master/Operador, que ficam vinculados às prefeituras para acessar o /app)
     * ou vínculo com OUTRA prefeitura NUNCA é excluído — apenas perde o vínculo
     * (o pivot tenant_user morre na cascata).
     */
    public static function usuariosDoTenant(int $tenantId): array
    {
        $vinculados = DB::table('tenant_user')
            ->where('tenant_id', $tenantId)
            ->pluck('user_id')
            ->unique()
            ->values();

        if ($vinculados->isEmpty()) {
            return [[], []];
        }

        $comOutroVinculo = DB::table('tenant_user')
            ->whereIn('user_id', $vinculados)
            ->where('tenant_id', '!=', $tenantId)
            ->distinct()
            ->pluck('user_id');

        $globais = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereNull('roles.tenant_id')
            ->where('model_has_roles.model_type', User::class)
            ->whereIn('model_has_roles.model_id', $vinculados)
            ->distinct()
            ->pluck('model_has_roles.model_id');

        $protegidos = $comOutroVinculo->merge($globais)->unique();

        return [
            $vinculados->diff($protegidos)->values()->all(),
            $vinculados->intersect($protegidos)->values()->all(),
        ];
    }

    /**
     * Executa a exclusão. Retorna o resumo do que foi feito.
     * A parte SQL roda numa transação única; arquivos só APÓS o commit.
     */
    public static function excluir(Tenant $tenant): array
    {
        $tenantId = $tenant->id;
        $slug = $tenant->slug;
        $nome = $tenant->name;

        // Tudo que depende das LINHAS precisa ser coletado ANTES da cascata
        [$idsExcluir, $idsDesvincular] = self::usuariosDoTenant($tenantId);
        $arquivos = self::coletarArquivos($tenant);
        $mockPath = \App\Services\ApiTools\IntegraPrefeituraService::caminhoMock($tenant);

        $linhasOrfas = [];
        $usuariosExcluidos = 0;

        DB::transaction(function () use ($tenantId, $idsExcluir, &$linhasOrfas, &$usuariosExcluidos) {
            // 1) A linha do tenant — o PostgreSQL cascateia ~97 tabelas aqui
            DB::table('tenants')->where('id', $tenantId)->delete();

            // 2) Varredura genérica: o que a cascata não alcançou (tabelas com
            //    tenant_id sem FK — viabilidades — e as do Spatie por team)
            foreach (self::tabelasComTenantId() as $tabela) {
                $n = DB::table($tabela)->where('tenant_id', $tenantId)->delete();
                if ($n > 0) {
                    $linhasOrfas[$tabela] = $n;
                }
            }

            // 3) Usuários exclusivos da prefeitura — exclusão FÍSICA
            $usuariosExcluidos = self::excluirUsuarios($idsExcluir);
        });

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // 4) Arquivos — irreversível de qualquer forma, então fora da transação
        $resumoArquivos = self::limparArquivos($arquivos, $slug, $mockPath);

        Log::info("[TenantExclusao] '{$nome}' ({$slug}) excluída definitivamente", [
            'usuarios_excluidos' => $usuariosExcluidos,
            'usuarios_desvinculados' => count($idsDesvincular),
            'linhas_orfas_limpas' => $linhasOrfas,
            'arquivos' => $resumoArquivos,
        ]);

        return [
            'nome' => $nome,
            'slug' => $slug,
            'usuarios_excluidos' => $usuariosExcluidos,
            'usuarios_desvinculados' => count($idsDesvincular),
            'linhas_orfas' => $linhasOrfas,
            'arquivos' => $resumoArquivos,
        ];
    }

    /**
     * Exclusão FÍSICA de usuários (bypassa o SoftDeletes de propósito) com as
     * dependências que não têm FK: tokens Sanctum, notificações e pivôs Spatie.
     * Reutilizado pelo tenant:limpar-orfaos.
     */
    public static function excluirUsuarios(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $ids)
            ->delete();

        if (Schema::hasTable('notifications')) {
            DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->whereIn('notifiable_id', $ids)
                ->delete();
        }

        if (Schema::hasTable('cadastrador_locations')) {
            DB::table('cadastrador_locations')->whereIn('user_id', $ids)->delete();
        }

        DB::table('model_has_roles')->where('model_type', User::class)->whereIn('model_id', $ids)->delete();
        DB::table('model_has_permissions')->where('model_type', User::class)->whereIn('model_id', $ids)->delete();
        DB::table('tenant_user')->whereIn('user_id', $ids)->delete();

        // Query builder = hard delete (ignora o SoftDeletes do model User)
        return DB::table('users')->whereIn('id', $ids)->delete();
    }

    /**
     * Caminhos de arquivo do disk 'public' pertencentes ao tenant.
     * Genérico: intersecta as colunas conhecidas de caminho com o schema real
     * de cada tabela do tenant — tabela/coluna nova com esses nomes entra sozinha.
     */
    public static function coletarArquivos(Tenant $tenant): array
    {
        $paths = [];

        $ehCaminhoLocal = fn ($v) => is_string($v) && $v !== ''
            && ! str_starts_with($v, 'data:')
            && ! str_starts_with($v, 'http');

        foreach (self::tabelasComTenantId() as $tabela) {
            $colunas = Schema::getColumnListing($tabela);

            foreach (array_intersect(self::COLUNAS_ARQUIVO, $colunas) as $coluna) {
                $valores = DB::table($tabela)
                    ->where('tenant_id', $tenant->id)
                    ->whereNotNull($coluna)
                    ->pluck($coluna);

                foreach ($valores as $valor) {
                    if ($ehCaminhoLocal($valor)) {
                        $paths[] = $valor;
                    }
                }
            }

            foreach (array_intersect(self::COLUNAS_ARQUIVO_JSON, $colunas) as $coluna) {
                $valores = DB::table($tabela)
                    ->where('tenant_id', $tenant->id)
                    ->whereNotNull($coluna)
                    ->pluck($coluna);

                foreach ($valores as $json) {
                    $lista = is_string($json) ? json_decode($json, true) : $json;
                    foreach ((array) $lista as $item) {
                        if ($ehCaminhoLocal($item)) {
                            $paths[] = $item;
                        }
                    }
                }
            }
        }

        $logo = data_get($tenant->data, 'logo');
        if ($ehCaminhoLocal($logo)) {
            $paths[] = $logo;
        }

        return array_values(array_unique($paths));
    }

    /**
     * Remove os arquivos do tenant: disk public, mock tributário, nuvem de
     * pontos (Potree) e o prefixo {slug}/ no bucket R2 (best-effort).
     */
    public static function limparArquivos(array $paths, string $slug, ?string $mockPath): array
    {
        $resumo = ['arquivos_public' => count($paths), 'mock' => false, 'nuvem_pontos' => false, 'r2' => 'não configurado'];

        foreach (array_chunk($paths, 500) as $chunk) {
            try {
                Storage::disk('public')->delete($chunk);
            } catch (\Throwable $e) {
                Log::warning('[TenantExclusao] falha ao apagar arquivos public: '.$e->getMessage());
            }
        }

        if ($mockPath && is_file($mockPath)) {
            File::delete($mockPath);
            $resumo['mock'] = true;
        }

        $potree = public_path('nuvem-pontos/'.$slug);
        if (is_dir($potree)) {
            File::deleteDirectory($potree);
            $resumo['nuvem_pontos'] = true;
        }

        try {
            if (config('filesystems.disks.midia.key')) {
                Storage::disk('midia')->deleteDirectory($slug);
                $resumo['r2'] = 'prefixo removido';
            }
        } catch (\Throwable $e) {
            $resumo['r2'] = 'FALHOU: '.$e->getMessage();
            Log::warning("[TenantExclusao] R2 {$slug}/: ".$e->getMessage());
        }

        return $resumo;
    }
}
