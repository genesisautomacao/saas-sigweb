<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;

class PessoaSaudeCondicao extends Model
{
    // Usamos o BelongsToTenant para garantir a blindagem Multi-Tenant
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'pessoa_saude_condicoes';

    protected $fillable = [
        'tenant_id',
        'pessoa_id',
        'is_hipertenso',
        'is_diabetico',
        'is_gestante',
        'is_fumante',
        'is_pcd',
        'is_acamado',
        'ultima_sincronizacao',
    ];

    protected function casts(): array
    {
        return [
            'is_hipertenso' => 'boolean',
            'is_diabetico' => 'boolean',
            'is_gestante' => 'boolean',
            'is_fumante' => 'boolean',
            'is_pcd' => 'boolean',
            'is_acamado' => 'boolean',
            'ultima_sincronizacao' => 'datetime',
        ];
    }

    /**
     * Retorna a Pessoa dona desta ficha médica
     */
    public function pessoa()
    {
        return $this->belongsTo(Pessoa::class);
    }
}