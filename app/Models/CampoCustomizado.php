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
        'obrigatorio', 'obrigatorio_coleta', 'leitura_coleta', 'na_coleta', 'ordem', 'ativo',
    ];

    protected $casts = [
        'opcoes' => 'array',
        'obrigatorio' => 'boolean',
        // obrigatorio = formulários do sistema WEB · obrigatorio_coleta = boletim/app.
        // São INDEPENDENTES (campo pode ser opcional no cadastro e exigido em campo).
        'obrigatorio_coleta' => 'boolean',
        'leitura_coleta' => 'boolean', // true = aparece no app SOMENTE leitura
        'na_coleta' => 'boolean',
        'ativo' => 'boolean',
    ];

    /**
     * Camadas que aceitam campos customizados.
     *
     * O item 75 do edital pede campo customizado "vinculando o mesmo a sua respectiva
     * Camada (Layer)" — por isso a lista cobre o cadastro imobiliário inteiro, e não só
     * as 3 entidades originais do R67-1.
     */
    public const ENTIDADES = [
        'lote' => 'Lote',
        'edificacao' => 'Edificação',
        'unidade' => 'Unidade Imobiliária',
        'quadra' => 'Quadra',
        'bairro' => 'Bairro',
        'logradouro' => 'Logradouro',
        'secao_logradouro' => 'Seção de Logradouro',
        'lote_testada' => 'Testada do Lote',
        'loteamento' => 'Loteamento',
        'zona' => 'Zoneamento',
        'perimetro' => 'Distrito / Perímetro',
        'setor_fiscal' => 'Setor Fiscal',
        'meio_fio' => 'Meio-fio / Calçada',
        'mob_trecho' => 'Trecho Viário (Mobilidade)',
        'mob_eixo' => 'Eixo de Mobilidade',
    ];

    /**
     * Camadas que o cadastrador preenche EM CAMPO (entram no boletim do app).
     * As demais aceitam campo customizado para ficha, filtro, temático e estatística,
     * mas não fazem sentido no boletim — ninguém coleta "zoneamento" na rua.
     */
    public const ENTIDADES_COLETAVEIS = ['lote', 'edificacao', 'unidade'];

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
