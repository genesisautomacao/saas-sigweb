<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class HistoricoFaseChamado extends Model
{
    use BelongsToTenant;

    protected $table = 'historico_fases_chamado';

    protected $fillable = ['tenant_id', 'chamado_id', 'fase_id', 'user_id'];

    public function chamado()
    {
        return $this->belongsTo(Chamado::class, 'chamado_id');
    }

    public function fase()
    {
        return $this->belongsTo(FaseChamado::class, 'fase_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
