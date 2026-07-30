<?php

namespace App\Console\Commands;

use App\Models\Pessoa;
use App\Models\UnidadeImobiliaria;
use Illuminate\Console\Command;

class SincronizarTudoApi extends Command
{
    // O comando que você vai digitar no terminal
    protected $signature = 'sigweb:sincronizar-imoveis {tenant_id}';

    protected $description = 'Sincroniza todas as unidades imobiliárias com a API da prefeitura extraindo os endereços e proprietários.';

    public function handle()
    {
        $tenantId = $this->argument('tenant_id');
        $apiService = app(\App\Services\ApiTools\IntegraPrefeituraService::class);

        $unidades = UnidadeImobiliaria::where('tenant_id', $tenantId)
            ->whereNotNull('codigo_imovel_tributario')
            ->get();

        $this->info("Iniciando sincronização de {$unidades->count()} imóveis para o Tenant ID: {$tenantId}...");

        // Cria uma barra de progresso visual no terminal!
        $bar = $this->output->createProgressBar($unidades->count());
        $bar->start();

        $sucesso = 0;

        foreach ($unidades as $unidade) {
            try {
                $dados = $apiService->buscarImovel([
                    'codigo_imovel_tributario' => $unidade->codigo_imovel_tributario,
                    'inscricao_imobiliaria' => $unidade->inscricao_imobiliaria,
                ], $tenantId);

                if ($dados) {
                    $nomeProprietario = $dados['proprietario_name'] ?? null;
                    $pessoaId = null;

                    if ($nomeProprietario) {
                        $pessoa = Pessoa::firstOrCreate(
                            ['name' => $nomeProprietario, 'tenant_id' => $tenantId],
                            ['type' => 'fisica']
                        );
                        $pessoaId = $pessoa->id;
                    }

                    $logradouroNome = trim(($dados['tipo_logradouro'] ?? '').' '.($dados['logradouro'] ?? ''));

                    $unidade->update([
                        // ?? — payload pode não trazer a inscrição (mantém a atual)
                        'inscricao_imobiliaria' => $dados['inscricao_imobiliaria'] ?? $unidade->inscricao_imobiliaria,
                        'logradouro_nome' => $logradouroNome ?: null,
                        'numero_imovel' => (string) ($dados['numero_logradouro'] ?? 'S/N'),
                        'proprietario_id' => $pessoaId,
                        'dados_tributarios' => $dados,
                    ]);
                    $sucesso++;
                }
            } catch (\Exception $e) {
                // Silencia o erro de um imóvel para não parar a cidade toda
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ Concluído! {$sucesso} imóveis sincronizados com sucesso.");
    }
}
