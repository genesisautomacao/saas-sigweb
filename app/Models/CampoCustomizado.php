<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * R67-1 — definição de um campo customizado do município para lote/edificação/unidade.
 * Os VALORES ficam na coluna JSON `dados_customizados` da entidade (chave = slug).
 */
class CampoCustomizado extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'campos_customizados';

    protected $fillable = [
        'tenant_id', 'entidade', 'label', 'slug', 'tipo', 'opcoes',
        'obrigatorio', 'na_coleta', 'ordem', 'ativo',
    ];

    protected $casts = [
        'opcoes' => 'array',
        'obrigatorio' => 'boolean',
        'na_coleta' => 'boolean',
        'ativo' => 'boolean',
    ];

    /** Entidades que aceitam campos customizados. */
    public const ENTIDADES = [
        'lote' => 'Lote',
        'edificacao' => 'Edificação',
        'unidade' => 'Unidade Imobiliária',
    ];

    /** Tipos de campo suportados (o app renderiza o boletim a partir disto). */
    public const TIPOS = [
        'texto' => 'Texto',
        'numero' => 'Número',
        'selecao' => 'Seleção única',
        'multipla' => 'Múltipla escolha',
        'data' => 'Data',
        'sim_nao' => 'Sim / Não',
    ];
}
