<?php

namespace App\Policies;

use App\Models\LeadSource;
use App\Models\User;

class LeadSourcePolicy
{
    // Todos que veem leads precisam ver os status para preencher o formulário
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('manage_crm_settings');
    }

    public function view(User $user, LeadSource $model): bool
    {
        return $user->hasPermissionTo('manage_crm_settings');
    }

    // Apenas quem tem permissão de GERENCIAR configurações do CRM pode criar/editar
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_crm_settings');
    }

    public function update(User $user, LeadSource $model): bool
    {
        return $user->hasPermissionTo('manage_crm_settings');
    }

    public function delete(User $user, LeadSource $model): bool
    {
        return $user->hasPermissionTo('manage_crm_settings');
    }
}