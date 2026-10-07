<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\UnidadeImobiliaria;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarDadosTributarios extends Command
{
    protected $signature = 'tributario:importar
                            {--tenant= : Slug do município (ex: antonio-carlos)}
                            {--file=   : Caminho do arquivo JSON com os dados tributários}
                            {--dry-run : Simula a importação sem salvar}';

    protected $description = 'Importa dados tributários de um JSON municipal para unidades imobiliárias (por inscricao_imobiliaria)';

    public function handle(): int
    {
        $tenantSlug = $this->option('tenant');
        $filePath = $this->option('file');
        $dryRun = $this->option('dry-run');

        if (! $tenantSlug || ! $filePath) {
            $this->error('Informe --tenant e --file. Exemplo:');
            $this->line('  php artisan tributario:importar --tenant=antonio-carlos --file=dados.json');

            return 1;
        }

        if (! file_exists($filePath)) {
            $this->error("Arquivo não encontrado: {$filePath}");

            return 1;
        }

        $tenant = Tenant::where('slug', $tenantSlug)->first();
        if (! $tenant) {
            $this->error("Tenant '{$tenantSlug}' não encontrado.");

            return 1;
        }

        // Bases municipais inteiras (Cajazeiras: 44 MB / 39 mil imóveis) não cabem no
        // memory_limit padrão do PHP-CLI.
        ini_set('memory_limit', '2048M');

        $json = json_decode(file_get_contents($filePath), true);
        if (! is_array($json)) {
            $this->error('JSON inválido ou não é um array de imóveis.');

            return 1;
        }

        // Array raiz, {"imoveis": [...]} ou GeoJSON FeatureCollection (campos em properties).
        // Os valores entram como estão no arquivo (mesmo comportamento de antes).
        $imoveis = \App\Services\ApiTools\IntegraPrefeituraService::normalizarImoveis($json);

        if (empty($imoveis)) {
            $this->warn('Nenhum imóvel encontrado no JSON.');

            return 0;
        }

        $this->info("Tenant: {$tenant->name} (id={$tenant->id})");
        $this->info('Imóveis no JSON: '.count($imoveis));
        $dryRun && $this->warn('-- DRY RUN: nenhuma alteração será salva --');

        $atualizados = 0;
        $naoEncontrados = 0;
        $semInscricao = 0;

        $bar = $this->output->createProgressBar(count($imoveis));
        $bar->start();

        DB::beginTransaction();

        try {
            foreach ($imoveis as $imovel) {
                // R67-5 — o de/para roda ANTES de qualquer lookup: um JSON com os nomes
                // do sistema de origem (ex.: nrInscricao → inscricao_imobiliaria) também
                // importa. O payload bruto é preservado dentro do próprio array.
                $imovel = \App\Services\Fiscal\MapaFiscalService::aplicar($imovel, $tenant->id);

                $inscricao = $imovel['inscricao_imobiliaria'] ?? null;

                if (! $inscricao) {
                    // Antes era pulado em silêncio — "Atualizados: 0" sem explicação quando
                    // o de/para não aponta nenhum campo para inscricao_imobiliaria.
                    $semInscricao++;
                    $bar->advance();

                    continue;
                }

                $unidade = UnidadeImobiliaria::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('inscricao_imobiliaria', $inscricao)
                    ->first();

                if (! $unidade) {
                    $naoEncontrados++;
                    $bar->advance();

                    continue;
                }

                if (! $dryRun) {
                    $unidade->dados_tributarios = $imovel;
                    // Atualiza também código tributário se vier no JSON
                    if (! empty($imovel['codigo_imovel_tributario'])) {
                        $unidade->codigo_imovel_tributario = $imovel['codigo_imovel_tributario'];
                    }
                    $unidade->save();

                    // Propaga area_cadastrada para o lote pai (usado no comparativo cartográfico)
                    if ($unidade->lote_id && isset($imovel['area_geo'])) {
                        DB::table('lotes')
                            ->where('id', $unidade->lote_id)
                            ->update(['area_cadastrada' => (float) $imovel['area_geo']]);
                    }
                }

                $atualizados++;
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $this->newLine();
            $this->error('Erro durante importação: '.$e->getMessage());

            return 1;
        }

        $this->info("Atualizados: {$atualizados}");
        $naoEncontrados > 0 && $this->warn("Não encontrados (inscrição sem correspondência): {$naoEncontrados}");
        $semInscricao > 0 && $this->warn("Sem inscrição após o de/para (ignorados): {$semInscricao} — este comando casa por inscricao_imobiliaria; para vincular pelo código tributário use sigweb:sincronizar-imoveis.");

        return 0;
    }
}
