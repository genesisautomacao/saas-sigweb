<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Acesso ao painel /admin do SaaS.
 *
 * Cria o papel GLOBAL "Operador" (roles.tenant_id = null, mesmo mecanismo do Master) e
 * as permissões admin_* que viram as caixas de seleção do cadastro "Usuários do Admin".
 * O papel não recebe permissão nenhuma: as capacidades são concedidas POR USUÁRIO
 * (permissões diretas globais) — ver User::sincronizarAcessoAdmin().
 *
 * Idempotente: pode rodar quantas vezes for preciso.
 *   php artisan db:seed --class=AdminAcessoSeeder
 */
class AdminAcessoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (array_keys(User::CAPACIDADES_ADMIN) as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach (User::PAPEIS_ADMIN as $papel) {
            Role::firstOrCreate(['name' => $papel, 'tenant_id' => null, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
