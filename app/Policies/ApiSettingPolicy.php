<?php

namespace App\Policies;

use App\Models\ApiSetting;
use App\Models\User;

/**
 * Credenciais globais de terceiros (Resend, etc.) — exclusivas do Master.
 * Um erro aqui derruba o e-mail transacional de TODAS as prefeituras.
 */
class ApiSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isMaster();
    }

    public function view(User $user, ApiSetting $apiSetting): bool
    {
        return $user->isMaster();
    }

    public function create(User $user): bool
    {
        return $user->isMaster();
    }

    public function update(User $user, ApiSetting $apiSetting): bool
    {
        return $user->isMaster();
    }

    public function delete(User $user, ApiSetting $apiSetting): bool
    {
        return $user->isMaster();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isMaster();
    }
}
