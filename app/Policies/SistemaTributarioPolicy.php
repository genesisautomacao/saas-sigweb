<?php

namespace App\Policies;

use App\Models\SistemaTributario;
use App\Models\User;

/**
 * Catálogo global de sistemas tributários (de/para, chave de ligação, conector) —
 * exclusivo do Master: uma alteração aqui vale para todas as prefeituras do sistema.
 */
class SistemaTributarioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isMaster();
    }

    public function view(User $user, SistemaTributario $sistemaTributario): bool
    {
        return $user->isMaster();
    }

    public function create(User $user): bool
    {
        return $user->isMaster();
    }

    public function update(User $user, SistemaTributario $sistemaTributario): bool
    {
        return $user->isMaster();
    }

    public function delete(User $user, SistemaTributario $sistemaTributario): bool
    {
        return $user->isMaster();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isMaster();
    }
}
