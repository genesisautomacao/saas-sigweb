<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de tipos de sinalização viária (Mobilidade Urbana — decisão 6.1
 * de docs/piuma.txt): a placa no mapa NÃO carrega texto livre — aponta para
 * um tipo pré-cadastrado com nome, tipo (vertical/horizontal), cor e ícone.
 * O que varia entre duas placas "Pare" é só a posição.
 *
 * Sem SoftDeletes: a FK restrict de mob_sinalizacoes impede apagar tipo em
 * uso; `ativo = false` aposenta o tipo sem quebrar o histórico.
 */
class MobTipoSinalizacao extends Model
{
    use BelongsToTenant;

    public const TIPOS = [
        'vertical' => 'Vertical',
        'horizontal' => 'Horizontal',
    ];

    protected $table = 'mob_tipos_sinalizacao';

    protected $fillable = [
        'tenant_id', 'name', 'tipo', 'cor', 'icone', 'codigo_ctb', 'ordem', 'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'ordem' => 'integer',
    ];

    public function sinalizacoes()
    {
        return $this->hasMany(MobSinalizacao::class, 'tipo_sinalizacao_id');
    }
}
