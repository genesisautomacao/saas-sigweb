<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B9 (2026-10-20) — a engenharia da Líder pediu outro nome para o setor da
 * inscrição imobiliária criado na R85: `setores_fiscais` → `setores_cadastrais`
 * (SetorFiscal → SetorCadastral, bairros.setor_fiscal_id → setor_cadastral_id,
 * permissões *_setores_fiscais → *_setores_cadastrais). Na VPS a tabela estava
 * vazia (conferido em 2026-10-20); a migration preserva o que houver mesmo assim.
 *
 * Só age se `setores_fiscais` for a tabela da INSCRIÇÃO (sem pgv_parametro_id) —
 * nunca toca no setor da PGV.
 */
return new class extends Migration
{
    private const PERMISSOES = [
        'view_setores_fiscais' => 'view_setores_cadastrais',
        'create_setores_fiscais' => 'create_setores_cadastrais',
        'edit_setores_fiscais' => 'edit_setores_cadastrais',
        'delete_setores_fiscais' => 'delete_setores_cadastrais',
        'ver_camada_setores_fiscais' => 'ver_camada_setores_cadastrais',
    ];

    public function up(): void
    {
        if (Schema::hasTable('setores_fiscais') && ! Schema::hasTable('setores_cadastrais')
            && ! Schema::hasColumn('setores_fiscais', 'pgv_parametro_id')) {
            DB::statement('ALTER TABLE setores_fiscais RENAME TO setores_cadastrais');
            $this->renomearObjetos('setores_cadastrais', 'setores_fiscais_', 'setores_cadastrais_');
        }

        if (Schema::hasColumn('bairros', 'setor_fiscal_id')) {
            DB::statement('ALTER TABLE bairros RENAME COLUMN setor_fiscal_id TO setor_cadastral_id');
            $this->renomearObjetos('bairros', 'bairros_setor_fiscal_id', 'bairros_setor_cadastral_id');
        }

        $this->renomearPermissoes(self::PERMISSOES);
        $this->trocarValores('setor_fiscal', 'setor_cadastral', 'App\\Models\\SetorFiscal', 'App\\Models\\SetorCadastral');
    }

    public function down(): void
    {
        if (Schema::hasColumn('bairros', 'setor_cadastral_id')) {
            DB::statement('ALTER TABLE bairros RENAME COLUMN setor_cadastral_id TO setor_fiscal_id');
            $this->renomearObjetos('bairros', 'bairros_setor_cadastral_id', 'bairros_setor_fiscal_id');
        }

        if (Schema::hasTable('setores_cadastrais') && ! Schema::hasTable('setores_fiscais')) {
            DB::statement('ALTER TABLE setores_cadastrais RENAME TO setores_fiscais');
            $this->renomearObjetos('setores_fiscais', 'setores_cadastrais_', 'setores_fiscais_');
        }

        $this->renomearPermissoes(array_flip(self::PERMISSOES));
        $this->trocarValores('setor_cadastral', 'setor_fiscal', 'App\\Models\\SetorCadastral', 'App\\Models\\SetorFiscal');
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
            foreach (DB::table('permissions')->where('name', $de)->get() as $perm) {
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
