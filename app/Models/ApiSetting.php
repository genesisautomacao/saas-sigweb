<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuração global de uma API de terceiros (Resend, AWS S3, WhatsApp…).
 *
 * NÃO é escopada por tenant — é uma credencial única, gerenciada pelo super usuário
 * no painel Admin (ApiSettingResource) e consumida globalmente pelo AppServiceProvider.
 */
class ApiSetting extends Model
{
    protected $fillable = ['name', 'data'];

    protected $casts = [
        'data' => 'array',
    ];

    protected static function booted(): void
    {
        // O AppServiceProvider carrega TODAS as linhas com cache eterno — invalida
        // aqui para a credencial nova valer na requisição seguinte.
        $limpar = fn () => \Illuminate\Support\Facades\Cache::forget('api_settings.all');
        static::saved($limpar);
        static::deleted($limpar);
    }
}
