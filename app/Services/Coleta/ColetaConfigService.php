<?php

namespace App\Services\Coleta;

use App\Models\Tenant;
use App\Models\User;

/**
 * R67-3 — configuração do boletim de coleta entregue ao app.
 * O app monta o formulário inteiro a partir deste payload (rótulos, listas e campos
 * customizados do município + região do cadastrador), sem precisar de release do app
 * quando o município muda um vocabulário.
 */
class ColetaConfigService
{
    /** Campos base do LOTE que podem ser exigidos no boletim. */
    public const CAMPOS_BASE_LOTE = [
        'ocupacao' => 'Ocupação do lote',
        'situacao_quadra' => 'Situação na quadra',
        'observacao' => 'Observação',
        'foto_frontal' => 'Foto frontal',
        'foto_lateral_esq' => 'Foto lateral esquerda',
        'foto_lateral_dir' => 'Foto lateral direita',
    ];

    /** Campos base da EDIFICAÇÃO que podem ser exigidos no boletim. */
    public const CAMPOS_BASE_EDIFICACAO = [
        'tipo' => 'Finalidade / uso',
        'tp_construcao' => 'Tipo de construção',
        'caracteristica_construcao' => 'Característica da construção',
        'estado_conservacao' => 'Estado de conservação',
        'pavimento' => 'Nº de pavimentos',
        'area_geo' => 'Área construída',
    ];

    public static function config(Tenant $tenant, User $user): array
    {
        $tenantId = $tenant->id;
        $base = (array) data_get($tenant->data, 'coleta_campos_base', []);

        return [
            // Quais campos BASE o município exige no boletim (vazio = nenhum obrigatório)
            'campos_base' => [
                'lote' => array_values((array) ($base['lote'] ?? [])),
                'edificacao' => array_values((array) ($base['edificacao'] ?? [])),
            ],

            // Campos padrão com o rótulo/lista do município (white-label)
            'campos_padrao' => [
                'lote' => CampoDominioService::paraApp('lote', $tenantId),
                'edificacao' => CampoDominioService::paraApp('edificacao', $tenantId),
            ],

            // Campos criados pelo município
            'campos_customizados' => [
                'lote' => CampoCustomizadoService::paraApp('lote', $tenantId),
                'edificacao' => CampoCustomizadoService::paraApp('edificacao', $tenantId),
                'unidade' => CampoCustomizadoService::paraApp('unidade', $tenantId),
            ],

            // Região atribuída (null = sem restrição; ausência de quadras = não baixa nada)
            'regiao' => ColetaRegiaoService::resumoRegiao($tenantId, $user->id),
        ];
    }
}
