<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

/**
 * Painel /admin — quem faz o quê com as Prefeituras.
 *
 * Master tem bypass total (Gate::before do AppServiceProvider); o Operador só executa
 * o que estiver marcado no cadastro dele. EXCLUIR prefeitura é sempre exclusivo do Master.
 */
class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminSaas();
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->isAdminSaas();
    }

    public function create(User $user): bool
    {
        return $user->podeNoAdmin('admin_criar_prefeitura');
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->podeNoAdmin('admin_editar_prefeitura');
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->isMaster();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isMaster();
    }

    public function restore(User $user, Tenant $tenant): bool
    {
        return $user->isMaster();
    }

    public function forceDelete(User $user, Tenant $tenant): bool
    {
        return $user->isMaster();
    }
}
