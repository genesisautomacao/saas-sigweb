<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R67-5 (revisada em 2026-07-30) — CATÁLOGO GLOBAL de sistemas tributários.
 *
 * O de/para de campos é característica do SISTEMA (Betha, GOVBR, IPM, Fiorilli…),
 * não da prefeitura: dez prefeituras Betha usam o mesmo mapa. O catálogo vive no
 * painel /admin do SaaS (SistemaTributarioResource) e cada tenant aponta para uma
 * entrada via `tenant.data['sistema_tributario_id']` (select no TenantResource).
 *
 * O JSON bruto recebido continua sendo a verdade (unidade_imobiliarias.dados_tributarios);
 * o mapa traduz os nomes de origem para o modelo CANÔNICO do SIGWEB (13 colunas fiscais)
 * e declara campos extras do sistema local a exibir no BIC. Tenant sem sistema apontado
 * = comportamento atual (chaves já canônicas).
 *
 * Nota: substitui a tabela por-tenant `integracao_fiscal_mapas` da 1ª versão da R67,
 * que nunca chegou a produção.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sistemas_tributarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();   // "Betha", "GOVBR", "Betha — layout 2"…
            $table->text('observacao')->nullable();
            // Conector de API real deste sistema (chave do registry
            // IntegraPrefeituraService::DRIVERS; null = sem API — só de/para p/ arquivos).
            // A prefeitura só consegue ligar o modo "produção" se o sistema tiver driver.
            $table->string('driver')->nullable();
            // PONTO DE LIGAÇÃO: qual campo da unidade localiza o imóvel no sistema
            // do fornecedor (uns localizam pelo código do cadastro, outros pela inscrição).
            $table->string('chave_ligacao')->default('codigo_imovel_tributario');
            $table->json('mapa')->nullable();   // {campo_origem: campo_canonico}
            $table->json('extras')->nullable(); // [{origem, label}]
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sistemas_tributarios');
    }
};
