<?php

namespace App\Services\ApiTools\Drivers;

use App\Models\Tenant;

/**
 * Contrato de um conector de API REAL de sistema tributário (Betha, GOVBR, IPM…).
 *
 * Um driver por FORNECEDOR (registrado em IntegraPrefeituraService::DRIVERS e
 * apontado na coluna `driver` do catálogo Sistemas Tributários). As CREDENCIAIS
 * são por prefeitura (tenant.data['tributario_api'] = ['url' => ..., 'token' => ...]),
 * preenchidas na seção "Integração Tributária" do TenantResource.
 *
 * O driver devolve o payload BRUTO do fornecedor (nomes de origem) — o de/para
 * do catálogo traduz depois, no IntegraPrefeituraService (ponto único).
 * Falha de comunicação: lançar exceção (o service loga e devolve null — NUNCA
 * cai no mock em produção).
 */
interface TributarioDriver
{
    /**
     * Busca um imóvel na API do fornecedor pelo PONTO DE LIGAÇÃO do sistema.
     *
     * @param  array{url?: string, token?: string}  $credenciais  credenciais da prefeitura
     * @param  string  $chaveLigacao  campo que localiza o imóvel neste fornecedor
     *                                (`codigo_imovel_tributario` | `inscricao_imobiliaria` —
     *                                configurado no catálogo, coluna `chave_ligacao`)
     * @param  string  $valor  o valor desse campo na unidade imobiliária
     * @return array<string, mixed>|null payload bruto do fornecedor, ou null se não encontrado
     */
    public function buscarImovel(Tenant $tenant, array $credenciais, string $chaveLigacao, string $valor): ?array;
}
