<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * R67-5 — entrada do CATÁLOGO GLOBAL de sistemas tributários (painel /admin).
 * NÃO é escopada por tenant: o de/para pertence ao sistema (Betha, GOVBR, IPM…),
 * e cada prefeitura aponta para uma entrada via tenant.data['sistema_tributario_id'].
 * Consumida pelo MapaFiscalService na importação/sincronização tributária.
 */
class SistemaTributario extends Model
{
    /**
     * Campos da unidade imobiliária que podem ser o PONTO DE LIGAÇÃO com o
     * sistema do fornecedor (qual valor localiza o imóvel na API/arquivo dele).
     */
    public const CHAVES_LIGACAO = [
        'codigo_imovel_tributario' => 'Código do imóvel (tributário)',
        'inscricao_imobiliaria' => 'Inscrição imobiliária',
    ];

    protected $table = 'sistemas_tributarios';

    protected $fillable = ['nome', 'observacao', 'driver', 'chave_ligacao', 'mapa', 'extras', 'ativo'];

    protected $casts = [
        'mapa' => 'array',
        'extras' => 'array',
        'ativo' => 'boolean',
    ];
}
