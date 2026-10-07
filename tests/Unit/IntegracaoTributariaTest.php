<?php

namespace Tests\Unit;

use App\Services\ApiTools\IntegraPrefeituraService;
use App\Services\Fiscal\ProprietarioService;
use Tests\TestCase;

/**
 * Integração tributária em escala (Cajazeiras, 2026-10-07) — partes puras, sem banco:
 * normalização dos 3 formatos de mock, limpeza de valores no upload, índice por chave
 * e validação de CPF/CNPJ/nome do ProprietarioService.
 */
class IntegracaoTributariaTest extends TestCase
{
    public function test_normaliza_array_na_raiz_e_chave_imoveis(): void
    {
        $lista = [['codigo_imovel_tributario' => '1', 'proprietario_name' => 'A']];

        $this->assertSame($lista, IntegraPrefeituraService::normalizarImoveis($lista));
        $this->assertSame($lista, IntegraPrefeituraService::normalizarImoveis(['imoveis' => $lista]));
    }

    public function test_normaliza_geojson_extraindo_properties_e_descartando_geometria(): void
    {
        $geojson = [
            'type' => 'FeatureCollection',
            'name' => 'cadastro_imobiliario',
            'features' => [
                ['type' => 'Feature', 'properties' => ['CODIGO_IMOVEL' => '19337', 'N_PESSOA' => 'ELIAS'], 'geometry' => null],
                ['type' => 'Feature', 'properties' => null, 'geometry' => null], // ignorada
                ['type' => 'Feature', 'properties' => ['CODIGO_IMOVEL' => '19338'], 'geometry' => ['type' => 'Point', 'coordinates' => [0, 0]]],
            ],
        ];

        $this->assertSame(
            [['CODIGO_IMOVEL' => '19337', 'N_PESSOA' => 'ELIAS'], ['CODIGO_IMOVEL' => '19338']],
            IntegraPrefeituraService::normalizarImoveis($geojson),
        );

        // json_decode sem assoc (stdClass) também entra
        $this->assertCount(2, IntegraPrefeituraService::normalizarImoveis(json_decode(json_encode($geojson))));
    }

    public function test_normalizacao_nao_altera_valores_dos_mocks_existentes(): void
    {
        $lista = [['proprietario_name' => '  Fulano ', 'numero_logradouro' => '', 'obs' => 'NULL']];

        // leitura = estrutura apenas; os valores saem como estão no arquivo
        $this->assertSame($lista, IntegraPrefeituraService::normalizarImoveis($lista));
    }

    public function test_normalizacao_devolve_null_para_entrada_invalida(): void
    {
        $this->assertNull(IntegraPrefeituraService::normalizarImoveis('texto'));
        $this->assertNull(IntegraPrefeituraService::normalizarImoveis([]));
        $this->assertNull(IntegraPrefeituraService::normalizarImoveis(['type' => 'FeatureCollection', 'features' => []]));
        $this->assertNull(IntegraPrefeituraService::normalizarImoveis(['imoveis' => 'x']));
        $this->assertNull(IntegraPrefeituraService::normalizarImoveis(['a' => 1])); // objeto solto não é lista
    }

    public function test_limpar_valores_apara_e_converte_null_literal(): void
    {
        $item = ['a' => '  x ', 'b' => 'NULL', 'c' => 'null', 'd' => '', 'e' => 12, 'f' => ['NULL'], 'g' => null];

        $this->assertSame(
            ['a' => 'x', 'b' => null, 'c' => null, 'd' => null, 'e' => 12, 'f' => ['NULL'], 'g' => null],
            IntegraPrefeituraService::limparValores($item),
        );
    }

    public function test_indexar_primeiro_vence_apara_e_ignora_vazios(): void
    {
        $imoveis = [
            ['codigo_imovel_tributario' => ' 00.022.1111 ', 'nome' => 'primeiro'],
            ['codigo_imovel_tributario' => '00.022.1111', 'nome' => 'segundo'],
            ['codigo_imovel_tributario' => '', 'nome' => 'vazio'],
            ['outra' => '1'],
            ['codigo_imovel_tributario' => 123, 'nome' => 'numerico'],
            'lixo',
        ];

        $indice = IntegraPrefeituraService::indexar($imoveis, 'codigo_imovel_tributario');

        // chave numérica vira int no PHP — isset($indice['123']) continua funcionando
        $this->assertSame(['00.022.1111', '123'], array_map('strval', array_keys($indice)));
        $this->assertSame('primeiro', $indice['00.022.1111']['nome']);
        $this->assertSame('numerico', $indice['123']['nome']);
    }

    public function test_cpf_valido_rejeita_codigo_de_imovel_disfarcado(): void
    {
        $this->assertSame('52998224725', ProprietarioService::cpfValido('529.982.247-25'));
        $this->assertSame('52998224725', ProprietarioService::cpfValido(52998224725));
        $this->assertNull(ProprietarioService::cpfValido('00000019337')); // CODIGO_IMOVEL com zeros (Cajazeiras)
        $this->assertNull(ProprietarioService::cpfValido('111.111.111-11'));
        $this->assertNull(ProprietarioService::cpfValido('5299822472'));
        $this->assertNull(ProprietarioService::cpfValido(null));
        $this->assertNull(ProprietarioService::cpfValido('NULL'));
    }

    public function test_cnpj_valido(): void
    {
        $this->assertSame('11222333000181', ProprietarioService::cnpjValido('11.222.333/0001-81'));
        $this->assertNull(ProprietarioService::cnpjValido('11.222.333/0001-82'));
        $this->assertNull(ProprietarioService::cnpjValido('00000000000000'));
        $this->assertNull(ProprietarioService::cnpjValido(''));
    }

    public function test_normalizar_nome_colapsa_espacos_e_descarta_curingas(): void
    {
        $this->assertSame('ELIAS PAULO DOS SANTOS', ProprietarioService::normalizarNome('  ELIAS   PAULO DOS SANTOS '));
        $this->assertNull(ProprietarioService::normalizarNome('DESCONHECIDO'));
        $this->assertNull(ProprietarioService::normalizarNome('Não Informado'));
        $this->assertNull(ProprietarioService::normalizarNome('não informado'));
        $this->assertNull(ProprietarioService::normalizarNome('-'));
        $this->assertNull(ProprietarioService::normalizarNome(''));
        $this->assertNull(ProprietarioService::normalizarNome(null));
        $this->assertNull(ProprietarioService::normalizarNome(['x']));
    }
}
