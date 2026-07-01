<?php

namespace App\Models;

use App\Models\Documento;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

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

    public function documentos()
    {
        return $this->morphMany(Documento::class, 'documentable');
    }

    /** Nome a exibir: prioriza pessoa cadastrada, cai para nome livre */
    public function getNomeDisplayAttribute(): string
    {
        return $this->pessoa?->name ?? $this->nome_falecido ?? '—';
    }
}
