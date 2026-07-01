<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Traits\BelongsToTenant;
use App\Observers\MembroFamiliaObserver;

#[ObservedBy(MembroFamiliaObserver::class)]
class MembroFamilia extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'membro_familias';

    protected $fillable = [
        'tenant_id',
        'cadastro_social_id',
        'pessoa_id',
        'parentesco',
        'representante_familiar',
    ];

    protected $casts = [
        'representante_familiar' => 'boolean',
    ];

    public function cadastroSocial()
    {
        return $this->belongsTo(CadastroSocial::class);
    }

    public function pessoa()
    {
        return $this->belongsTo(Pessoa::class);
    }
}