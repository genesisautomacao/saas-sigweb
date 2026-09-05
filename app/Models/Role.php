<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Papel (Spatie) com as flags de sistema (docs/Modulos_Permissoes.txt, D7):
 *  - papel_sistema: 'master' (global, /admin) | 'manager' (gestor da prefeitura,
 *    bypass total) | null (papel comum). Substitui a checagem por NOME.
 *  - todos_modulos: recebe automaticamente as permissões de todo módulo ligado
 *    (Tenant::sincronizarPapeisTodosModulos). O Manager nasce com true.
 */
class Role extends SpatieRole
{
    public const SISTEMA_MASTER = 'master';

    public const SISTEMA_MANAGER = 'manager';

    protected $casts = [
        'todos_modulos' => 'boolean',
    ];

    // Dizemos ao Filament a qual prefeitura esse papel pertence
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function ehDeSistema(): bool
    {
        return $this->papel_sistema !== null;
    }

    public function ehManager(): bool
    {
        return $this->papel_sistema === self::SISTEMA_MANAGER;
    }

    public function scopeDeSistema(Builder $query, string $tipo): Builder
    {
        return $query->where('papel_sistema', $tipo);
    }

    public function scopeTodosModulos(Builder $query): Builder
    {
        return $query->where('todos_modulos', true);
    }

    /** Papel Manager da prefeitura (por flag; fallback pelo nome legado). */
    public static function managerDe(int $tenantId): ?self
    {
        return static::where('tenant_id', $tenantId)->deSistema(self::SISTEMA_MANAGER)->first()
            ?? static::where('tenant_id', $tenantId)->where('name', 'Manager')->first();
    }
}
