<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class JazigoFalecido extends Model
{
    use BelongsToTenant;

    protected $table = 'jazigo_falecidos';

    protected $fillable = [
        'tenant_id',
        'jazigo_id',
        'pessoa_id',
        'nome_falecido',
        'data_obito',
        'data_sepultamento',
        'numero_certidao_obito',
        'observacao',
    ];

    protected $casts = [
        'data_obito'        => 'date',
        'data_sepultamento' => 'date',
    ];

    public function jazigo()
    {
        return $this->belongsTo(Jazigo::class);
    }

    public function pessoa()
    {
        return $this->belongsTo(Pessoa::class);
    }

    /** Nome a exibir: prioriza pessoa cadastrada, cai para nome livre */
    public function getNomeDisplayAttribute(): string
    {
        return $this->pessoa?->name ?? $this->nome_falecido ?? '—';
    }
}
