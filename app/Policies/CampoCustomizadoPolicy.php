<?php

namespace App\Policies;

use App\Models\CampoCustomizado;
use App\Models\User;

class CampoCustomizadoPolicy
{
    // temPermissao() (não hasPermissionTo): permissão não semeada no banco = nega,
    // sem lançar PermissionDoesNotExist (incidente de produção de 2026-08-05).
    public function viewAny(User $user): bool
    {
        return $user->temPermissao('gerenciar_campos_customizados');
    }

    public function view(User $user, CampoCustomizado $model): bool
    {
        return $user->temPermissao('gerenciar_campos_customizados');
    }

    public function create(User $user): bool
    {
        return $user->temPermissao('gerenciar_campos_customizados');
    }

    public function update(User $user, CampoCustomizado $model): bool
    {
        return $user->temPermissao('gerenciar_campos_customizados');
    }

    public function delete(User $user, CampoCustomizado $model): bool
    {
        return $user->temPermissao('gerenciar_campos_customizados');
    }
}
