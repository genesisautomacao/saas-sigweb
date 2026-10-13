<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Configuração global de uma API de terceiros (Resend, AWS S3, WhatsApp…).
 *
 * NÃO é escopada por tenant — é uma credencial única, gerenciada pelo super usuário
 * no painel Admin (ApiSettingResource) e consumida globalmente pelo AppServiceProvider.
 *
 * R83-7 (2026-10-13): flag `ativo`. Linha INATIVA vale como ausente para todo
 * consumidor — ativasCache() é a fonte única do cache `api_settings.all`.
 */
class ApiSetting extends Model
{
    public const CACHE = 'api_settings.all';

    protected $fillable = ['name', 'data', 'ativo'];

    protected $casts = [
        'data' => 'array',
        'ativo' => 'boolean',
    ];

    protected static function booted(): void
    {
        // O AppServiceProvider carrega as linhas ATIVAS com cache eterno — invalida
        // aqui para a credencial nova (ou a flag) valer na requisição seguinte.
        $limpar = fn () => Cache::forget(self::CACHE);
        static::saved($limpar);
        static::deleted($limpar);
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /** Linhas ativas, chaveadas pelo nome, com cache eterno (invalidado no saved/deleted). */
    public static function ativasCache(): Collection
    {
        return Cache::rememberForever(self::CACHE, fn () => static::query()->ativas()->get()->keyBy('name'));
    }

    /** Dados (KeyValue) de uma linha ATIVA pelo nome; [] quando não existe ou está inativa. */
    public static function dados(string $name): array
    {
        return (array) (static::ativasCache()->get($name)?->data ?? []);
    }
}
