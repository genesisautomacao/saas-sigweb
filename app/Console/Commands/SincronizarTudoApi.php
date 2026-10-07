<?php

namespace App\Console\Commands;

use App\Models\Pessoa;
use App\Models\UnidadeImobiliaria;
use App\Services\ApiTools\IntegraPrefeituraService;
use App\Services\Fiscal\ProprietarioService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Varre as unidades imobiliárias da prefeitura e sincroniza cada uma pela integração
 * vigente (simulação/JSON ou API real), gravando o JSON do BIC, a inscrição, o endereço
 * e o PROPRIETÁRIO (Pessoa, via ProprietarioService — dedupe por CPF/CNPJ válido com
 * fallback no nome). É o único caminho que vincula `proprietario_id` em massa.
 *
 * Regras de preservação (2026-10-07): campo que a fonte NÃO traz não é sobrescrito —
 * endereço (tipo_logradouro/logradouro/numero_logradouro) só muda quando a chave vem no
 * payload, e o proprietário só muda quando a fonte traz um nome utilizável. Antes a
 * ressincronização gravava null/"S/N" e apagava o proprietário nesses casos.
 *
 * Base grande (Cajazeiras: 21.818 unidades × 39.705 imóveis) roda em minutos pela CLI:
 *   php artisan sigweb:sincronizar-imoveis {tenant_id}
 */
class SincronizarTudoApi extends Command
{
    protected $signature = 'sigweb:sincronizar-imoveis {tenant_id}';

    protected $description = 'Sincroniza todas as unidades imobiliárias com a API da prefeitura extraindo os endereços e proprietários.';

    public function handle()
    {
        // O mock de uma base municipal inteira fica em memória (indexado) durante a varredura.
        ini_set('memory_limit', '2048M');

        $tenantId = (int) $this->argument('tenant_id');
        $apiService = app(IntegraPrefeituraService::class);

        // A unidade precisa ter o identificador que ESTE sistema usa para localizar o imóvel.
        $chave = IntegraPrefeituraService::chaveLigacao($tenantId);

        $query = UnidadeImobiliaria::where('tenant_id', $tenantId)
            ->whereNotNull($chave)
            ->orderBy('id');

        $total = (clone $query)->count();
        $pessoasAntes = Pessoa::where('tenant_id', $tenantId)->count();

        $this->info("Iniciando sincronização de {$total} imóveis para o Tenant ID: {$tenantId} (chave de ligação: {$chave})...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $sucesso = 0;
        $semDados = 0;
        $semProprietario = 0;
        $falhas = 0;

        $query->chunkById(500, function ($unidades) use ($apiService, $tenantId, $bar, &$sucesso, &$semDados, &$semProprietario, &$falhas) {
            foreach ($unidades as $unidade) {
                try {
                    $dados = $apiService->buscarImovel([
                        'codigo_imovel_tributario' => $unidade->codigo_imovel_tributario,
                        'inscricao_imobiliaria' => $unidade->inscricao_imobiliaria,
                    ], $tenantId);

                    if (! $dados) {
                        $semDados++;
                        $bar->advance();

                        continue;
                    }

                    $update = ['dados_tributarios' => $dados];

                    // Payload pode não trazer a inscrição (mantém a atual)
                    if (filled($dados['inscricao_imobiliaria'] ?? null)) {
                        $update['inscricao_imobiliaria'] = $dados['inscricao_imobiliaria'];
                    }

                    if (array_key_exists('tipo_logradouro', $dados) || array_key_exists('logradouro', $dados)) {
                        $logradouroNome = trim(($dados['tipo_logradouro'] ?? '').' '.($dados['logradouro'] ?? ''));
                        $update['logradouro_nome'] = $logradouroNome ?: null;
                    }

                    if (array_key_exists('numero_logradouro', $dados)) {
                        $update['numero_imovel'] = (string) ($dados['numero_logradouro'] ?? 'S/N');
                    }

                    $pessoa = ProprietarioService::resolver($dados, $tenantId);

                    if ($pessoa) {
                        $update['proprietario_id'] = $pessoa->id;
                    } else {
                        $semProprietario++;
                    }

                    $unidade->update($update);
                    $sucesso++;
                } catch (\Throwable $e) {
                    // Um imóvel com problema não para a cidade toda — mas fica registrado.
                    $falhas++;
                    Log::warning('[sincronizar-imoveis] falha ao sincronizar unidade', [
                        'tenant_id' => $tenantId,
                        'unidade_id' => $unidade->id,
                        'codigo' => $unidade->codigo_imovel_tributario,
                        'erro' => $e->getMessage(),
                    ]);
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $pessoasCriadas = max(0, Pessoa::where('tenant_id', $tenantId)->count() - $pessoasAntes);

        // Linha única: a ação do /admin exibe tudo que vem depois de "Concluído!".
        $this->info(
            "✅ Concluído! {$sucesso} imóveis sincronizados com sucesso"
            ." · {$semDados} sem dados na fonte"
            ." · {$semProprietario} sem proprietário (nome vazio ou curinga)"
            ." · {$pessoasCriadas} pessoa(s) criada(s)"
            ." · {$falhas} falha(s)".($falhas > 0 ? ' (ver laravel.log)' : '').'.'
        );
    }
}
