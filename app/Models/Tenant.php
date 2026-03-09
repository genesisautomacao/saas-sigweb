<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Support\Facades\Storage;

class Tenant extends Model implements HasAvatar
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'modules',
        'data',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'modules' => 'array',
            'data' => 'array',
            'is_active' => 'boolean',
        ];
    }

    // --- A MÁGICA DOS EVENTOS ACONTECE AQUI ---
    protected static function booted(): void
    {
        // 1. Quando uma nova Empresa (Tenant) é CRIADA
        static::created(function (Tenant $tenant) {
            // Cria o papel Manager para esta Tenant
            $role = \Spatie\Permission\Models\Role::create([
                'name' => 'Manager',
                'tenant_id' => $tenant->id,
                'guard_name' => 'web',
            ]);

            // Executa a nossa rotina de sincronização de permissões
            $tenant->syncManagerPermissions($role);
        });

        // 2. Quando uma Empresa (Tenant) é ATUALIZADA (Ex: Você liberou um módulo novo)
        static::updated(function (Tenant $tenant) {
            // Só roda se o campo 'modules' tiver sofrido alguma alteração
            if ($tenant->wasChanged('modules')) {
                $role = \Spatie\Permission\Models\Role::where('name', 'Manager')
                    ->where('tenant_id', $tenant->id)
                    ->first();

                if ($role) {
                    $tenant->syncManagerPermissions($role);
                }
            }
        });
    }

    /**
     * Função auxiliar que aplica as regras de negócio e sincroniza as permissões do Manager
     */
    public function syncManagerPermissions(\Spatie\Permission\Models\Role $role): void
    {
        // Busca TODAS as permissões cadastradas no sistema
        $allPermissions = \Spatie\Permission\Models\Permission::where('guard_name', 'web')->pluck('name');
        
        // Pega os módulos ativos desta Tenant
        $activeModules = $this->modules ?? [];

        // Filtra as permissões com base nas suas regras de negócio
        $permissionsToAssign = $allPermissions->filter(function ($permission) use ($activeModules) {
            
            // REGRA 1: O Manager NUNCA tem a restrição de ver apenas os próprios leads
            if ($permission === 'view_my_leads' || $permission === 'ver-meus-leads') {
                return false;
            }

            // REGRA 2: Só libera as permissões de Leads se a Tenant tiver o módulo 'leads'
            if (str_ends_with($permission, '_leads') && !in_array('leads', $activeModules)) {
                return false;
            }

            // REGRA 3: Preparando o terreno para os futuros módulos (Frotas e Financeiro)
            if (str_ends_with($permission, '_frotas') && !in_array('frotas', $activeModules)) {
                return false;
            }
            if (str_ends_with($permission, '_financeiro') && !in_array('financeiro', $activeModules)) {
                return false;
            }

            // Se for permissão base do sistema (ex: view_users, create_roles), ele deixa passar direto
            return true; 
        });

        // Sincroniza o array filtrado diretamente no papel do Manager
        $role->syncPermissions($permissionsToAssign);
    }

    public function getFilamentAvatarUrl(): ?string
    {
        $logo = data_get($this->data, 'logo');
        
        if ($logo) {
            // O asset() cria o link perfeito para a web e não dá erro no editor
            return asset('storage/' . $logo);
        }
        
        return null;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    // 🛑 ADICIONE ESTES RELACIONAMENTOS AQUI 🛑
    
    // Módulo Administrativo
    public function pessoas(): HasMany
    {
        return $this->hasMany(Pessoa::class);
    }
    public function contatos(): HasMany
    {
        return $this->hasMany(Contato::class);
    }
    public function enderecos(): HasMany
    {
        return $this->hasMany(Endereco::class);
    }
    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    }

    // Módulo GIS (Mapa)
    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class);
    }
    public function quadras(): HasMany
    {
        return $this->hasMany(Quadra::class);
    }
    public function zonas(): HasMany
    {
        return $this->hasMany(Zona::class);
    }
    public function edificacoes(): HasMany
    {
        return $this->hasMany(Edificacao::class);
    }

    public function tipoPostes(): HasMany {
        return $this->hasMany(TipoPoste::class);
    }
    
    public function postes(): HasMany {
        return $this->hasMany(Poste::class);
    }

    public function arvores()
    {
        return $this->hasMany(Arvore::class);
    }

}