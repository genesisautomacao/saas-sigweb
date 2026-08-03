<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PoC Tangará (E1.6) — inventário de dados de demonstração.
 *
 * Responde: "esta prefeitura tem dado suficiente para demonstrar cada item do edital
 * que depende de DADO?" — para rodar NA VPS antes da convocação, em vez de descobrir
 * na frente da Comissão que a busca por CNPJ não retorna nada.
 *
 * Uso:
 *   php artisan poc:inventario --tenant=prefeitura-de-santa-cecilia
 *   php artisan poc:inventario            (lista os tenants e pede o slug)
 */
class PocInventario extends Command
{
    protected $signature = 'poc:inventario {--tenant= : Slug da prefeitura}';

    protected $description = 'Confere se a base da prefeitura exercita cada item do edital da PoC Tangará que depende de dado';

    public function handle(): int
    {
        $slug = $this->option('tenant');

        if (! $slug) {
            $this->table(['id', 'slug', 'name'], DB::table('tenants')->get(['id', 'slug', 'name'])->map(fn ($t) => (array) $t));
            $this->error('Informe --tenant=<slug>');

            return self::FAILURE;
        }

        $tenant = DB::table('tenants')->where('slug', $slug)->first();

        if (! $tenant) {
            $this->error("Tenant '{$slug}' não encontrado.");

            return self::FAILURE;
        }

        $t = $tenant->id;
        $this->info("Inventário de dados — {$tenant->name} (id {$t})");
        $this->newLine();

        $linhas = [];
        $lacunas = 0;

        $conta = function (string $tabela, ?string $where = null) use ($t): int|string {
            if (! Schema::hasTable($tabela)) {
                return 'TABELA AUSENTE';
            }
            try {
                $q = DB::table($tabela)->where('tenant_id', $t);
                if (Schema::hasColumn($tabela, 'deleted_at')) {
                    $q->whereNull('deleted_at');
                }
                if ($where) {
                    $q->whereRaw($where);
                }

                return (int) $q->count();
            } catch (\Throwable $e) {
                return 'ERRO: '.substr($e->getMessage(), 0, 50);
            }
        };

        $add = function (string $item, string $desc, $valor, int $minimo = 1) use (&$linhas, &$lacunas) {
            $ok = is_int($valor) && $valor >= $minimo;
            if (! $ok) {
                $lacunas++;
            }
            $linhas[] = [$item, $desc, (string) $valor, $ok ? 'OK' : '>>> FALTA'];
        };

        // ── Consulta de Dados (Intranet 3–21 / Internet 1–14) ───────────────
        $add('3 / I-1', 'Unidades com endereço (logradouro_nome)', $conta('unidade_imobiliarias', "logradouro_nome IS NOT NULL AND logradouro_nome <> ''"));
        $add('4 / I-2', 'Unidades com inscrição imobiliária', $conta('unidade_imobiliarias', 'inscricao_imobiliaria IS NOT NULL'));
        $add('5 / I-3', 'Unidades com NOME DE EDIFÍCIO', $conta('unidade_imobiliarias', "nome_edificio IS NOT NULL AND nome_edificio <> ''"));
        $add('6 / I-4', 'Loteamentos', $conta('loteamentos'));
        $add('7 / I-5', 'Quadras', $conta('quadras'));
        $add('8 / I-6', 'Distritos (perímetros urbanos)', $conta('perimetros_urbanos'));
        $add('9 / I-7', 'Setores fiscais', $conta('setores_fiscais'));
        $add('10 / I-8', 'Bairros', $conta('bairros'));
        $add('11a', 'Pessoas com CPF', $conta('pessoas', "cpf IS NOT NULL AND cpf <> ''"));
        $add('11b', 'Pessoas com CNPJ (busca por CNPJ)', $conta('pessoas', "cnpj IS NOT NULL AND cnpj <> ''"));
        $add('11c', 'Unidades com proprietário vinculado', $conta('unidade_imobiliarias', 'proprietario_id IS NOT NULL'));
        $add('12', 'Pessoas (total)', $conta('pessoas'));
        $add('13 / I-9', 'Lotes com FOTO FRONTAL', $conta('lotes', "foto_frontal IS NOT NULL AND foto_frontal <> ''"));
        $add('14', 'Registros de auditoria', Schema::hasTable('activity_log') ? (int) DB::table('activity_log')->count() : 'AUSENTE');
        $add('15', 'Lotes com geometria (memorial)', $conta('lotes', 'geo IS NOT NULL'));
        $add('17 / I-10', 'Seções de logradouro', $conta('secoes_logradouro'));
        $add('17b', 'Seções com CÓDIGO métrico', $conta('secoes_logradouro', "codigo IS NOT NULL AND codigo <> ''"));
        $add('17c', 'Seções com LADO', $conta('secoes_logradouro', "lado IS NOT NULL AND lado <> ''"));
        $add('18 / I-11', 'Zonas', $conta('zonas'));
        $add('18b', 'Parâmetros urbanos', $conta('parametros_urbanos'));
        $add('20 / I-13', 'Regras de zoneamento (CNAE)', $conta('zoneamento_regras'));
        $add('21 / I-14', 'Viabilidades emitidas', $conta('viabilidade_emissoes'));

        // ── Edição Cartográfica (42–61) ─────────────────────────────────────
        $add('42', 'Lotes (total)', $conta('lotes'));
        $add('42b', 'Lotes com OCUPAÇÃO', $conta('lotes', 'ocupacao IS NOT NULL'));
        $add('42c', 'Lotes com situação na quadra (campo do município)', $conta('lotes', "dados_customizados->>'situacao_quadra' IS NOT NULL"));
        $add('42d', 'Testadas de lote', $conta('lote_testadas'));
        $add('42e', 'Testadas VINCULADAS a seção', $conta('lote_testadas', 'secao_logradouro_id IS NOT NULL'));
        $add('43', 'Edificações', $conta('edificacoes'));
        $add('43b', 'Edificações com TIPO (campo do município)', $conta('edificacoes', "dados_customizados->>'tipo_edificacao' IS NOT NULL"));
        $add('43c', 'Edificações com PAVIMENTO (campo do município)', $conta('edificacoes', "dados_customizados->>'pavimento' IS NOT NULL"));
        $add('44', 'Logradouros com CÓDIGO', $conta('logradouros', "codigo IS NOT NULL AND codigo <> ''"));
        $add('45', 'Quadras com CÓDIGO', $conta('quadras', "codigo IS NOT NULL AND codigo <> ''"));
        $add('46', 'Distritos com CÓDIGO', $conta('perimetros_urbanos', "codigo IS NOT NULL AND codigo <> ''"));
        $add('47', 'Setores com CÓDIGO', $conta('setores_fiscais', "codigo IS NOT NULL AND codigo <> ''"));
        $add('48', 'Bairros com CÓDIGO', $conta('bairros', "codigo IS NOT NULL AND codigo <> ''"));
        $add('49', 'Zonas com CÓDIGO', $conta('zonas', "codigo IS NOT NULL AND codigo <> ''"));
        $add('57', 'Meio-fios', $conta('meio_fios'));

        // ── Campos customizados / coleta ────────────────────────────────────
        $add('75', 'Campos customizados definidos (kit)', $conta('campos_customizados'));
        $add('CTM', 'Coletas registradas', $conta('coleta_imobiliaria'));

        // ── Usuários ────────────────────────────────────────────────────────
        $add('83', 'Perfis do tenant', Schema::hasTable('roles') ? (int) DB::table('roles')->where('tenant_id', $t)->count() : 'AUSENTE');
        $add('84', 'Usuários vinculados', Schema::hasTable('tenant_user') ? (int) DB::table('tenant_user')->where('tenant_id', $t)->count() : 'AUSENTE');

        $this->table(['Item edital', 'O quê', 'Qtd', 'Status'], $linhas);
        $this->newLine();

        if ($lacunas > 0) {
            $this->warn("{$lacunas} lacuna(s) — itens do edital sem dado para demonstrar nesta base.");
        } else {
            $this->info('Nenhuma lacuna — todos os itens verificados têm dado.');
        }

        return self::SUCCESS;
    }
}
