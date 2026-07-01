<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InformacaoSocial extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'informacao_sociais';

    protected $fillable = ['tenant_id', 'sequential_id', 'name', 'descricao'];

    /**
     * Inverso de CadastroSocial::informacoes() — necessário para o AttachAction
     * do RelationManager de Definição Social (Filament usa a relação inversa).
     */
    public function cadastroSocials()
    {
        return $this->belongsToMany(CadastroSocial::class, 'familia_informacoes')
            ->withPivot(['id', 'valor'])
            ->withTimestamps();
    }
}

