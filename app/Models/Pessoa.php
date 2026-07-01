<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Pessoa extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'cpf', 'cnpj', 'type'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'tenant_id',
        'sequential_id',
        'name',
        'cpf',
        'cns',
        'esus_id',
        'birth_date',
        'death_date',
        'type',
        'cnpj',
        'trade_name',
        // Pessoa - Social (item 092)
        'rg',
        'ctps',
        'pis',
        'nis',
        'certidao_nascimento',
        'telefone',
        'estado_civil',
        'sexo',
        'pai_id',
        'mae_id',
        'conjuge_id',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'death_date' => 'date',
        ];
    }

    public function pai()
    {
        return $this->belongsTo(Pessoa::class, 'pai_id');
    }

    public function mae()
    {
        return $this->belongsTo(Pessoa::class, 'mae_id');
    }

    public function conjuge()
    {
        return $this->belongsTo(Pessoa::class, 'conjuge_id');
    }

    public function rendas()
    {
        return $this->hasMany(PessoaRenda::class);
    }

    public function deficiencias()
    {
        return $this->hasMany(PessoaDeficiencia::class);
    }

    public function ocorrencias()
    {
        return $this->morphMany(OcorrenciaSocial::class, 'ocorrenciavel');
    }

    public function contatos()
    {
        return $this->hasMany(Contato::class);
    }

    public function enderecos()
    {
        return $this->hasMany(Endereco::class);
    }

    // Polimorfismo para Documentos
    public function documentos()
    {
        return $this->morphMany(Documento::class, 'documentable');
    }

    // Relacionamento 1 para 1 com a tabela de Condições de Saúde do e-SUS
    public function condicoesSaude()
    {
        return $this->hasOne(PessoaSaudeCondicao::class);
    }
}