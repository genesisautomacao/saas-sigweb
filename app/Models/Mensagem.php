<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Mensagem extends Model
{
    use BelongsToTenant;

    protected $table = 'mensagens';

    protected $fillable = [
        'tenant_id',
        'remetente_id',
        'destinatario_id',
        'texto',
        'lido_em',
    ];

    protected $casts = [
        'lido_em' => 'datetime',
    ];

    public function remetente()
    {
        return $this->belongsTo(User::class, 'remetente_id');
    }

    public function destinatario()
    {
        return $this->belongsTo(User::class, 'destinatario_id');
    }
}
