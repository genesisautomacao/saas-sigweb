<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Observers\PessoaRendaObserver;

#[ObservedBy(PessoaRendaObserver::class)]
class PessoaRenda extends Model
{
    use BelongsToTenant;

    protected $table = 'pessoa_rendas';

    protected $fillable = [
        'tenant_id', 'pessoa_id', 'tipo_renda_id', 'valor', 'compoe_renda_familiar',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'compoe_renda_familiar' => 'boolean',
    ];

    public function pessoa()
    {
        return $this->belongsTo(Pessoa::class);
    }

    public function tipoRenda()
    {
        return $this->belongsTo(TipoRenda::class);
    }
}
