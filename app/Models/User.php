<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    // SoftDeletes: excluir um usuário nunca apaga a linha (vira deleted_at), preservando
    // a integridade das FKs de histórico (processo_tramitacoes, respostas, anexos, pessoas…).
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'tipo', // prefeitura | cidadao
        'password',
        'email_verified_at',
        'expo_push_token',
    ];

    /** Cidadão (auto-cadastro no painel do cidadão) — não acessa o painel da prefeitura. */
    public function isCidadao(): bool
    {
        return $this->tipo === 'cidadao';
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class);
    }

    // Registros de Pessoa vinculados a esta conta (um por tenant). Ver processosConceito.md §9.1.
    public function pessoas(): HasMany
    {
        return $this->hasMany(Pessoa::class, 'user_id');
    }

    // Setores/departamentos aos quais este usuário pertence (motor de Processos, item 1).
    public function setores(): BelongsToMany
    {
        return $this->belongsToMany(Setor::class, 'setor_user');
    }

    /** Pessoa deste User num tenant específico (ignora o escopo global de tenant). */
    public function pessoaNoTenant($tenantId): ?Pessoa
    {
        return Pessoa::withoutGlobalScopes()
            ->where('user_id', $this->id)
            ->where('tenant_id', $tenantId)
            ->first();
    }


    public function getTenants(Panel $panel): Collection
    {
        return $this->tenants;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->tenants()->whereKey($tenant)->exists();
    }

    // Controle de acesso aos painéis
    public function canAccessPanel(Panel $panel): bool
    {
        // Se estiver tentando acessar o painel Admin, verifica se tem o papel Master
        if ($panel->getId() === 'admin') {
            return $this->hasRole('Master');
        }

        // Painel da prefeitura: CIDADÃO não pode acessar (mesmo sem papel definido).
        if ($panel->getId() === 'app') {
            return !$this->isCidadao();
        }

        // Demais painéis (cidadão): o Filament ainda exige uma Tenant vinculada.
        return true;
    }
}