<?php

namespace App\Policies;

use App\Models\LeadStatus;
use App\Models\User;

class LeadStatusPolicy
{
    // Todos que veem leads precisam ver os status para preencher o formulário
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('manage_crm_settings');
    }

    public function view(User $user, LeadStatus $model): bool
    {
        return $user->hasPermissionTo('manage_crm_settings');
    }

    // Apenas quem tem permissão de GERENCIAR configurações do CRM pode criar/editar
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_crm_settings');
    }

    public function update(User $user, LeadStatus $model): bool
    {
        return $user->hasPermissionTo('manage_crm_settings');
    }

    public function delete(User $user, LeadStatus $model): bool
    {
        return $user->hasPermissionTo('manage_crm_settings');
    }
}