<?php

namespace App\Filament\Concerns;

use App\Models\ColetaImobiliaria;
use App\Services\Coleta\CampoCustomizadoService;
use App\Services\Coleta\CampoDominioService;

/**
 * Onda 4/5 (PoC Tangará) — opções de campo e de valor para os construtores de
 * consulta dos mapas (intranet E público). Colunas base da camada + campos
 * customizados do município (item 76: chave `custom:slug`, resolvida no backend
 * contra o JSONB `dados_customizados`).
 *
 * Requer a propriedade pública `$tenantId` na página que usar o trait.
 */
trait MontaOpcoesFiltroMapa
{
    public function opcoesCamposFiltro(?string $layer, bool $apenasNumericos = false): array
    {
        $base = match ($layer) {
            'lotes' => ['area_geo' => 'Área em m²', 'main_facade_length' => 'Testada (m)', 'numero_lote' => 'Número do Lote', 'status_cadastro' => 'Status de Coleta', 'ocupacao' => 'Ocupação'],
            'edificacoes' => ['area_geo' => 'Área Construída (m²)'],
            'arvores' => ['botanical_species' => 'Espécie Botânica', 'size' => 'Porte', 'phytosanitary_condition' => 'Condição Fitossanitária', 'general_state' => 'Estado Geral', 'trunk_diameter_dap' => 'DAP (cm)', 'total_height' => 'Altura Total (m)', 'risk_potential' => 'Potencial de Risco'],
            'postes' => ['structural_condition' => 'Condição Estrutural', 'luminaire_type' => 'Tipo de Luminária', 'lamp_power' => 'Potência da Lâmpada', 'height' => 'Altura (m)'],
            'cemiterios' => ['name' => 'Nome', 'area_geo' => 'Área (m²)'],
            'zonas' => ['name' => 'Nome', 'sigla' => 'Sigla', 'codigo' => 'Código'],
            'perimetros_urbanos' => ['name' => 'Nome', 'distrito' => 'Distrito', 'codigo' => 'Código'],
            'quadras' => ['name' => 'Quadra', 'codigo' => 'Código', 'area_geo' => 'Área (m²)'],
            'bairros' => ['name' => 'Nome', 'codigo' => 'Código', 'area_geo' => 'Área (m²)'],
            'logradouros' => ['name' => 'Nome', 'codigo' => 'Código', 'extensao_geo' => 'Extensão (m)'],
            'secoes_logradouro' => ['codigo' => 'Código', 'lado' => 'Lado', 'extensao_geo' => 'Extensão (m)'],
            'loteamentos' => ['name' => 'Nome'],
            'rural_propriedades' => ['area_geo' => 'Área em m² (area_geo)', 'codigo_car' => 'Código CAR'],
            'rural_estradas' => ['extensao_geo' => 'Extensão (m)', 'tipo_pavimento' => 'Tipo de Pavimento', 'condicao_trafego' => 'Condição'],
            'rural_pontes' => ['capacidade_carga_toneladas' => 'Capacidade (Toneladas)', 'material_construcao' => 'Material'],
            default => ['name' => 'Nome / Número'],
        };

        $numericasBase = ['area_geo', 'main_facade_length', 'extensao_geo', 'trunk_diameter_dap', 'total_height', 'risk_potential', 'height', 'lamp_power', 'capacidade_carga_toneladas'];

        if ($apenasNumericos) {
            $base = array_intersect_key($base, array_flip($numericasBase));
        }

        // Campos customizados do município (item 76① — D5)
        $entidade = array_search($layer, CampoCustomizadoService::ENTIDADE_TABELA, true);

        if ($entidade !== false) {
            foreach (CampoCustomizadoService::definicoes($entidade, $this->tenantId) as $campo) {
                if ($apenasNumericos && $campo->tipo !== 'numero') {
                    continue;
                }

                $base['custom:'.$campo->slug] = '★ '.$campo->label.' (campo do município)';
            }
        }

        return $base;
    }

    /**
     * Opções de VALOR quando o campo escolhido é de lista (customizado seleção/múltipla
     * ou fixo white-label). Null = campo livre (o form mostra TextInput).
     */
    public function opcoesValoresCampo(?string $layer, ?string $campo): ?array
    {
        if (blank($layer) || blank($campo)) {
            return null;
        }

        $entidade = array_search($layer, CampoCustomizadoService::ENTIDADE_TABELA, true);

        if (str_starts_with($campo, 'custom:')) {
            if ($entidade === false) {
                return null;
            }

            $def = CampoCustomizadoService::definicoes($entidade, $this->tenantId)
                ->firstWhere('slug', substr($campo, 7));

            if ($def && in_array($def->tipo, ['selecao', 'multipla'], true) && ! empty($def->opcoes)) {
                return array_combine($def->opcoes, $def->opcoes);
            }

            return null;
        }

        if ($campo === 'status_cadastro') {
            return ColetaImobiliaria::STATUS;
        }

        if ($entidade !== false) {
            $opcoes = CampoDominioService::opcoes($entidade, $campo, $this->tenantId);

            if (! empty($opcoes)) {
                return $opcoes;
            }
        }

        return null;
    }
}
