<?php

namespace App\Services\Fiscal;

use App\Models\SistemaTributario;
use App\Models\UnidadeImobiliaria;
use Illuminate\Support\Facades\DB;

/**
 * R67-5 — adaptador do sistema tributário (catálogo global por SISTEMA).
 *
 * O de/para de campos é característica do sistema (Betha, GOVBR, IPM, Fiorilli…),
 * parametrizado UMA vez no painel /admin (SistemaTributarioResource). Cada prefeitura
 * aponta para uma entrada do catálogo via `tenant.data['sistema_tributario_id']`
 * (select no TenantResource). A prefeitura não vê nem edita a integração.
 *
 * O payload BRUTO recebido continua sendo a verdade (gravado em
 * `unidade_imobiliarias.dados_tributarios`); este service traduz os nomes de origem
 * para o modelo CANÔNICO do SIGWEB (as 13 colunas fiscais + chaves conhecidas do BIC)
 * e expõe os campos EXTRAS do sistema local que devem aparecer no BIC/telas.
 *
 * Prefeitura sem sistema apontado = passthrough (chaves já canônicas). Nada quebra.
 */
class MapaFiscalService
{
    /** Cache por request: [tenantId] => SistemaTributario|null */
    protected static array $cache = [];

    /**
     * Traduz o payload bruto para o canônico, PRESERVANDO as chaves originais
     * (o BIC e a auditoria continuam enxergando o que veio do sistema da prefeitura).
     */
    public static function aplicar(array $bruto, int $tenantId): array
    {
        $mapa = self::sistemaDoTenant($tenantId)?->mapa ?? [];

        if (empty($mapa)) {
            return $bruto;
        }

        $saida = $bruto;

        foreach ($mapa as $origem => $canonico) {
            if (! filled($canonico) || ! array_key_exists($origem, $bruto)) {
                continue;
            }

            // Só preenche o canônico se ele ainda não veio pronto no payload.
            if (! array_key_exists($canonico, $saida) || $saida[$canonico] === null || $saida[$canonico] === '') {
                $saida[$canonico] = $bruto[$origem];
            }
        }

        return $saida;
    }

    /**
     * Campos extras do sistema local a exibir (BIC/telas): [['label' => ..., 'valor' => ...]].
     */
    public static function extras(array $bruto, int $tenantId): array
    {
        $extras = self::sistemaDoTenant($tenantId)?->extras ?? [];
        $saida = [];

        foreach ($extras as $item) {
            $origem = $item['origem'] ?? null;

            if (! filled($origem) || ! array_key_exists($origem, $bruto)) {
                continue;
            }

            $valor = $bruto[$origem];

            $saida[] = [
                'label' => $item['label'] ?? $origem,
                'valor' => is_array($valor) ? implode(', ', $valor) : (string) $valor,
            ];
        }

        return $saida;
    }

    /** Campos canônicos disponíveis para o de/para (Select da tela de configuração). */
    public static function camposCanonicos(): array
    {
        $fiscais = array_combine(UnidadeImobiliaria::CAMPOS_FISCAIS, UnidadeImobiliaria::CAMPOS_FISCAIS);

        // Chaves canônicas fora das 13 colunas, usadas pelo BIC / propagação de endereço.
        $extras = [
            'proprietario_name' => 'proprietario_name',
            'proprietario_cpf' => 'proprietario_cpf',
            'inscricao_imobiliaria' => 'inscricao_imobiliaria',
            'codigo_imovel_tributario' => 'codigo_imovel_tributario',
            'tipo_logradouro' => 'tipo_logradouro',
            'logradouro' => 'logradouro',
            'numero_logradouro' => 'numero_logradouro',
            'cep' => 'cep',
            'testada' => 'testada',
            'area_geo' => 'area_geo',
        ];

        return array_merge($fiscais, $extras);
    }

    /** Resolve a prefeitura → entrada ATIVA do catálogo global de sistemas. */
    public static function sistemaDoTenant(int $tenantId): ?SistemaTributario
    {
        if (! array_key_exists($tenantId, self::$cache)) {
            // DB::table: sem model scopes; roda igual em web, admin, comando e fila.
            $data = DB::table('tenants')->where('id', $tenantId)->value('data');
            $sistemaId = data_get(is_string($data) ? json_decode($data, true) : $data, 'sistema_tributario_id');

            self::$cache[$tenantId] = $sistemaId
                ? SistemaTributario::where('id', (int) $sistemaId)->where('ativo', true)->first()
                : null;
        }

        return self::$cache[$tenantId];
    }

    public static function limparCache(): void
    {
        self::$cache = [];
    }
}
