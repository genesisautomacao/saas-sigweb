<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ViabilidadeEmissao extends Model
{
    use BelongsToTenant;

    protected $table = 'viabilidade_emissoes';

    protected $fillable = [
        'tenant_id',
        'protocolo',
        'hash_seguranca',
        'tipo',
        'status',
        'numero_lote',
        'inscricao_imobiliaria',
        'lote_id',
        'unidade_imobiliaria_id',
        'dados_snapshot',
        'emitido_por',
    ];

    protected $casts = [
        'dados_snapshot' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function emissor()
    {
        return $this->belongsTo(User::class, 'emitido_por');
    }

    public static function gerarProtocolo(string $prefixo): string
    {
        do {
            $protocolo = $prefixo . '-' . date('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(4));
        } while (self::withoutGlobalScopes()->where('protocolo', $protocolo)->exists());

        return $protocolo;
    }
}
