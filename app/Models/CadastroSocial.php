<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;

class CadastroSocial extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId; // <-- As Traits Mágicas

    protected $table = 'cadastros_sociais';

    protected $fillable = [
        'tenant_id',
        'sequential_id',
        'pessoa_id',
        'unidade_imobiliaria_id',
        'nis',
        'quantidade_membros',
        'renda_familiar_total',
        'renda_per_capita',
        'em_area_de_risco',
        'recebe_beneficios',
        'possui_membro_com_deficiencia',
        'situacao_moradia',
        'observacoes_tecnicas',
    ];

    protected $casts = [
        'em_area_de_risco' => 'boolean',
        'recebe_beneficios' => 'boolean',
        'possui_membro_com_deficiencia' => 'boolean',
        'renda_familiar_total' => 'decimal:2',
        'renda_per_capita' => 'decimal:2',
        'quantidade_membros' => 'integer',
    ];

    public function responsavel()
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }

    public function unidadeImobiliaria()
    {
        return $this->belongsTo(UnidadeImobiliaria::class, 'unidade_imobiliaria_id');
    }

    public function membros()
    {
        return $this->hasMany(MembroFamilia::class, 'cadastro_social_id');
    }

    public function setRendaFamiliarTotalAttribute($value)
    {
        $this->attributes['renda_familiar_total'] = $value;
        
        $membros = $this->attributes['quantidade_membros'] ?? 1;
        if ($membros > 0 && $value > 0) {
            $this->attributes['renda_per_capita'] = $value / $membros;
        } else {
            $this->attributes['renda_per_capita'] = 0;
        }
    }
}