<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B9 (2026-10-19) — o setor que existia era o da PGV (zona de valor ligada ao
 * `pgv_parametros`) e se chamava `setores_fiscais`. Passa a `setores_pgv`, para
 * o nome `setores_fiscais` ficar com o setor da INSCRIÇÃO imobiliária (base
 * cartográfica), criado na migration seguinte.
 *
 * Renomeia: tabela + constraints/índices/sequência (os nomes de índice são
 * globais no Postgres — sem isso a tabela nova colidiria), as FKs
 * `setor_fiscal_id` das faces e do histórico de valores, as 5 permissões (os
 * papéis seguem com elas, o pivô liga pelo id), `campos_customizados.entidade`
 * e o `subject_type` da Auditoria.
 */
return new class extends Migration
{
    private const PERMISSOES = [
        'view_setores_fiscais' => 'view_setores_pgv',
        'create_setores_fiscais' => 'create_setores_pgv',
        'edit_setores_fiscais' => 'edit_setores_pgv',
        'delete_setores_fiscais' => 'delete_setores_pgv',
        'ver_camada_setores_fiscais' => 'ver_camada_setores_pgv',
    ];

    private const FKS = ['face_quadras', 'lote_valores_historicos'];

    public function up(): void
    {
        // Idempotente: já renomeada (ou a nova já existe) = nada a fazer na tabela
        if (Schema::hasTable('setores_fiscais') && ! Schema::hasTable('setores_pgv')
            && Schema::hasColumn('setores_fiscais', 'pgv_parametro_id')) {
            DB::statement('ALTER TABLE setores_fiscais RENAME TO setores_pgv');
            $this->renomearObjetos('setores_pgv', 'setores_fiscais_', 'setores_pgv_');
        }

        foreach (self::FKS as $tabela) {
            if (Schema::hasColumn($tabela, 'setor_fiscal_id')) {
                DB::statement("ALTER TABLE {$tabela} RENAME COLUMN setor_fiscal_id TO setor_pgv_id");
                $this->renomearObjetos($tabela, "{$tabela}_setor_fiscal_id", "{$tabela}_setor_pgv_id");
            }
        }

        $this->renomearPermissoes(self::PERMISSOES);
        $this->trocarValores('setor_fiscal', 'setor_pgv', 'App\\Models\\SetorFiscal', 'App\\Models\\SetorPgv');
    }

    public function down(): void
    {
        foreach (self::FKS as $tabela) {
            if (Schema::hasColumn($tabela, 'setor_pgv_id')) {
                DB::statement("ALTER TABLE {$tabela} RENAME COLUMN setor_pgv_id TO setor_fiscal_id");
                $this->renomearObjetos($tabela, "{$tabela}_setor_pgv_id", "{$tabela}_setor_fiscal_id");
            }
        }

        if (Schema::hasTable('setores_pgv') && ! Schema::hasTable('setores_fiscais')) {
            DB::statement('ALTER TABLE setores_pgv RENAME TO setores_fiscais');
            $this->renomearObjetos('setores_fiscais', 'setores_pgv_', 'setores_fiscais_');
        }

        $this->renomearPermissoes(array_flip(self::PERMISSOES));
        $this->trocarValores('setor_pgv', 'setor_fiscal', 'App\\Models\\SetorPgv', 'App\\Models\\SetorFiscal');
    }

    /** Constraints, índices e sequências da tabela cujo nome começa com $de. */
    private function renomearObjetos(string $tabela, string $de, string $para): void
    {
        $padrao = str_replace('_', '\\_', $de).'%';

        $constraints = DB::select(
            'SELECT conname FROM pg_constraint WHERE conrelid = ?::regclass AND conname LIKE ?',
            [$tabela, $padrao]
        );
        foreach ($constraints as $c) {
            $novo = $para.substr($c->conname, strlen($de));
            DB::statement("ALTER TABLE {$tabela} RENAME CONSTRAINT \"{$c->conname}\" TO \"{$novo}\"");
        }

        // Os índices de PK/unique já foram junto com a constraint; sobram os avulsos
        $indices = DB::select(
            'SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname LIKE ?',
            [$tabela, $padrao]
        );
        foreach ($indices as $i) {
            $novo = $para.substr($i->indexname, strlen($de));
            DB::statement("ALTER INDEX \"{$i->indexname}\" RENAME TO \"{$novo}\"");
        }

        $sequencias = DB::select(
            "SELECT relname FROM pg_class WHERE relkind = 'S' AND relnamespace = current_schema()::regnamespace AND relname LIKE ?",
            [$padrao]
        );
        foreach ($sequencias as $s) {
            $novo = $para.substr($s->relname, strlen($de));
            DB::statement("ALTER SEQUENCE \"{$s->relname}\" RENAME TO \"{$novo}\"");
        }
    }

    /** Renomeia mantendo os vínculos; se o nome novo já existir (seeder rodou antes), funde. */
    private function renomearPermissoes(array $mapa): void
    {
        foreach ($mapa as $de => $para) {
            $antiga = DB::table('permissions')->where('name', $de)->get();
            foreach ($antiga as $perm) {
                $existente = DB::table('permissions')
                    ->where('name', $para)->where('guard_name', $perm->guard_name)->value('id');

                if (! $existente) {
                    DB::table('permissions')->where('id', $perm->id)->update(['name' => $para]);

                    continue;
                }

                DB::statement(
                    'INSERT INTO role_has_permissions (permission_id, role_id)
                     SELECT ?, role_id FROM role_has_permissions WHERE permission_id = ?
                     ON CONFLICT DO NOTHING',
                    [$existente, $perm->id]
                );
                DB::statement(
                    'INSERT INTO model_has_permissions (permission_id, model_type, model_id, tenant_id)
                     SELECT ?, model_type, model_id, tenant_id FROM model_has_permissions WHERE permission_id = ?
                     ON CONFLICT DO NOTHING',
                    [$existente, $perm->id]
                );
                DB::table('permissions')->where('id', $perm->id)->delete();
            }
        }
    }

    private function trocarValores(string $entidadeDe, string $entidadePara, string $classeDe, string $classePara): void
    {
        foreach (['campos_customizados', 'campo_dominios'] as $tabela) {
            if (Schema::hasColumn($tabela, 'entidade')) {
                DB::table($tabela)->where('entidade', $entidadeDe)->update(['entidade' => $entidadePara]);
            }
        }

        if (Schema::hasTable('activity_log')) {
            DB::table('activity_log')->where('subject_type', $classeDe)->update(['subject_type' => $classePara]);
        }
    }
};
