<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Setor/Departamento do motor de Processos Digitais (swimlanes — item 206).
 * Ver processosConceito.md §9.6. Distinto de SetorFiscal (PGV).
 */
class Setor extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'setores';

    protected $fillable = ['tenant_id', 'sequential_id', 'nome', 'cor'];

    public function etapas()
    {
        return $this->hasMany(BpmnEtapa::class);
    }

    /** Usuários que pertencem a este setor (item 1) — quem pode ver/agir nas etapas do setor. */
    public function users()
    {
        return $this->belongsToMany(User::class, 'setor_user');
    }
}
