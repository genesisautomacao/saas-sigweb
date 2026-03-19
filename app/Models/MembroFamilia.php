<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class MembroFamilia extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'membro_familias';

    protected $fillable = [
        'tenant_id',
        'cadastro_social_id',
        'pessoa_id',
        'parentesco',
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