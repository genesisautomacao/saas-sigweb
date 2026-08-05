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
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    // SoftDeletes: excluir um usuário nunca apaga a linha (vira deleted_at), preservando
    // a integridade das FKs de histórico (processo_tramitacoes, respostas, anexos, pessoas…).
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

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

    /**
     * Checagem de permissão À PROVA de permissão inexistente no banco.
     *
     * ⚠️ Incidente de produção (2026-08-05): `hasPermissionTo()`/`can()` do Spatie
     * LANÇAM PermissionDoesNotExist quando o nome não está na tabela `permissions`
     * (deploy sem rodar o PermissionsSeeder) — e como Policies/canAccess rodam na
     * montagem do menu, TODA tela quebrava para usuários sem bypass. Aqui, permissão
     * não semeada = simplesmente "não pode" (falha fechada, sem 500).
     * Use este método em Policies e canAccess de Pages/Resources.
     */
    public function temPermissao(string $permissao): bool
    {
        try {
            return $this->can($permissao);
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
            return false;
        }
    }

    /**
     * Papéis GLOBAIS (roles.tenant_id = null) que dão acesso ao painel /admin do SaaS.
     * Master = super administrador (bypass total via Gate::before).
     * Operador = acesso restrito às capacidades marcadas no cadastro do usuário.
     */
    public const PAPEL_MASTER = 'Master';

    public const PAPEL_OPERADOR = 'Operador';

    public const PAPEIS_ADMIN = [self::PAPEL_MASTER, self::PAPEL_OPERADOR];

    /**
     * Capacidades do Operador no painel /admin (permissões globais, marcadas por usuário).
     * Tudo o que NÃO estiver aqui é exclusivo do Master: excluir prefeitura, Configurações
     * de APIs, Sistemas Tributários, credenciais de integração e gestão destes usuários.
     */
    public const CAPACIDADES_ADMIN = [
        'admin_editar_prefeitura' => 'Editar dados da prefeitura',
        'admin_criar_prefeitura' => 'Cadastrar nova prefeitura',
        'admin_gerenciar_modulos' => 'Alterar módulos contratados',
        'admin_importar_gis' => 'Importar mapa (GIS)',
        'admin_recalcular_gis' => 'Recalcular áreas (GIS)',
        'admin_tributario_simulacao' => 'Simulação tributária (enviar JSON)',
        'admin_tributario_sincronizar' => 'Sincronizar tributário (todos)',
        'admin_sincronizar_esus' => 'Sincronizar e-SUS',
        'admin_delegar_manager' => 'Delegar Manager da prefeitura',
    ];

    /** Cache do papel global resolvido (evita repetir a consulta a cada checagem na tela). */
    protected ?string $papelAdminCache = null;

    protected bool $papelAdminResolvido = false;

    /**
     * Papel global deste usuário no painel do SaaS (Master > Operador), ou null.
     * O whereNull explícito garante que um papel homônimo criado dentro de uma
     * prefeitura (roles.tenant_id preenchido) nunca dê acesso ao /admin.
     */
    public function papelAdmin(): ?string
    {
        if (! $this->papelAdminResolvido) {
            $this->papelAdminCache = $this->roles()
                ->whereNull('roles.tenant_id')
                ->whereIn('roles.name', self::PAPEIS_ADMIN)
                ->orderByRaw('case when roles.name = ? then 0 else 1 end', [self::PAPEL_MASTER])
                ->value('roles.name');

            $this->papelAdminResolvido = true;
        }

        return $this->papelAdminCache;
    }

    /** Super administrador do SaaS. */
    public function isMaster(): bool
    {
        return $this->papelAdmin() === self::PAPEL_MASTER;
    }

    /** Tem acesso ao painel /admin (Master ou Operador). */
    public function isAdminSaas(): bool
    {
        return $this->papelAdmin() !== null;
    }

    /** O usuário pode executar esta capacidade no painel /admin? */
    public function podeNoAdmin(string $capacidade): bool
    {
        if (! $this->isAdminSaas()) {
            return false;
        }

        return $this->isMaster() || $this->can($capacidade);
    }

    /**
     * Define o acesso deste usuário ao painel /admin.
     *
     * Mexe SOMENTE nos vínculos globais (tenant_id null) — papéis e permissões que o
     * usuário tenha dentro de prefeituras (ex.: Manager de um município) ficam intactos,
     * o que o syncRoles()/syncPermissions() do Spatie não garante com teams ligado.
     *
     * @param  string|null  $papel  Master, Operador ou null (revoga o acesso ao /admin)
     * @param  array<int, string>  $capacidades  chaves de CAPACIDADES_ADMIN (ignorado para Master)
     */
    public function sincronizarAcessoAdmin(?string $papel, array $capacidades = []): void
    {
        $capacidades = $papel === self::PAPEL_OPERADOR
            ? array_values(array_intersect($capacidades, array_keys(self::CAPACIDADES_ADMIN)))
            : [];

        \Illuminate\Support\Facades\DB::transaction(function () use ($papel, $capacidades) {
            // 1) Papel global: remove os do SaaS e reatribui o escolhido
            \Illuminate\Support\Facades\DB::table('model_has_roles')
                ->where('model_id', $this->getKey())
                ->where('model_type', $this->getMorphClass())
                ->whereNull('tenant_id')
                ->whereIn('role_id', \Spatie\Permission\Models\Role::whereNull('tenant_id')
                    ->whereIn('name', self::PAPEIS_ADMIN)->pluck('id'))
                ->delete();

            if ($papel) {
                $roleId = \Spatie\Permission\Models\Role::whereNull('tenant_id')
                    ->where('name', $papel)->value('id');

                if ($roleId) {
                    \Illuminate\Support\Facades\DB::table('model_has_roles')->insert([
                        'role_id' => $roleId,
                        'model_type' => $this->getMorphClass(),
                        'model_id' => $this->getKey(),
                        'tenant_id' => null,
                    ]);
                }
            }

            // 2) Capacidades: permissões diretas globais (só as admin_*)
            \Illuminate\Support\Facades\DB::table('model_has_permissions')
                ->where('model_id', $this->getKey())
                ->where('model_type', $this->getMorphClass())
                ->whereNull('tenant_id')
                ->whereIn('permission_id', \Spatie\Permission\Models\Permission::whereIn('name', array_keys(self::CAPACIDADES_ADMIN))->pluck('id'))
                ->delete();

            foreach (\Spatie\Permission\Models\Permission::whereIn('name', $capacidades)->pluck('id') as $permissionId) {
                \Illuminate\Support\Facades\DB::table('model_has_permissions')->insert([
                    'permission_id' => $permissionId,
                    'model_type' => $this->getMorphClass(),
                    'model_id' => $this->getKey(),
                    'tenant_id' => null,
                ]);
            }
        });

        $this->papelAdminResolvido = false;
        $this->papelAdminCache = null;
        $this->unsetRelation('roles')->unsetRelation('permissions');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Capacidades globais atualmente concedidas a este usuário no /admin. */
    public function capacidadesAdmin(): array
    {
        return $this->permissions()
            ->whereIn('name', array_keys(self::CAPACIDADES_ADMIN))
            ->pluck('name')
            ->all();
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
        // Painel do SaaS: Master (super admin) ou Operador (acesso restrito por capacidades).
        // Ambos são papéis GLOBAIS (roles.tenant_id = null) — ver papelAdmin().
        if ($panel->getId() === 'admin') {
            return $this->isAdminSaas();
        }

        // Painel da prefeitura: CIDADÃO não pode acessar (mesmo sem papel definido).
        // Operador do SaaS sem prefeitura vinculada também não — a casa dele é o /admin.
        if ($panel->getId() === 'app') {
            if ($this->isCidadao()) {
                return false;
            }

            return $this->isAdminSaas()
                ? $this->tenants()->exists()
                : true;
        }

        // Demais painéis (cidadão): o Filament ainda exige uma Tenant vinculada.
        return true;
    }
}
