<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Endereco extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $fillable = [
        'tenant_id',
        'sequential_id',
        'pessoa_id',
        'cep',
        'address',
        'number',
        'complement',
        'neighborhood',
        'city',
        'state',
    ];

    public function pessoa()
    {
        // Forçando a chave estrangeira para evitar qualquer erro de mapeamento
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }
}