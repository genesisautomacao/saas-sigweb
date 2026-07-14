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
}
