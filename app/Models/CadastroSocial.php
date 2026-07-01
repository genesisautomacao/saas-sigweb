<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use App\Observers\CadastroSocialObserver;

#[ObservedBy(CadastroSocialObserver::class)]
class CadastroSocial extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId; // <-- As Traits Mágicas

    protected $table = 'cadastros_sociais';

    protected $fillable = [
        'tenant_id',
        'sequential_id',
        'pessoa_id',
        'unidade_imobiliaria_id',
        'empreendimento_id',
        'nis',
        'situacao_cadastro',
        'quantidade_membros',
        'renda_familiar_total',
        'renda_per_capita',
        'em_area_de_risco',
        'recebe_beneficios',
        'possui_membro_com_deficiencia',
        'situacao_moradia',
        'possui_terreno',
        'terreno_loteamento_id',
        'terreno_quadra_id',
        'terreno_lote_id',
        'terreno_titularidade',
        'indice_vulnerabilidade',
        'observacoes_tecnicas',
    ];

    protected $casts = [
        'em_area_de_risco' => 'boolean',
        'recebe_beneficios' => 'boolean',
        'possui_membro_com_deficiencia' => 'boolean',
        'possui_terreno' => 'boolean',
        'renda_familiar_total' => 'decimal:2',
        'renda_per_capita' => 'decimal:2',
        'quantidade_membros' => 'integer',
        'indice_vulnerabilidade' => 'integer',
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

    public function empreendimento()
    {
        return $this->belongsTo(Empreendimento::class);
    }

    public function terrenoLoteamento()
    {
        return $this->belongsTo(Loteamento::class, 'terreno_loteamento_id');
    }

    public function terrenoQuadra()
    {
        return $this->belongsTo(Quadra::class, 'terreno_quadra_id');
    }

    public function terrenoLote()
    {
        return $this->belongsTo(Lote::class, 'terreno_lote_id');
    }

    public function ocorrencias()
    {
        return $this->morphMany(OcorrenciaSocial::class, 'ocorrenciavel');
    }

    public function informacoes()
    {
        return $this->belongsToMany(InformacaoSocial::class, 'familia_informacoes')
            ->withPivot(['id', 'tenant_id', 'valor'])
            ->withTimestamps();
    }
}