<?php

namespace App\Policies;

use App\Models\{Programa, User};

class ProgramaPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('gerenciar_programas'); }
    public function view(User $user, Programa $model): bool { return $user->hasPermissionTo('gerenciar_programas'); }
    public function create(User $user): bool { return $user->hasPermissionTo('gerenciar_programas'); }
    public function update(User $user, Programa $model): bool { return $user->hasPermissionTo('gerenciar_programas'); }
    public function delete(User $user, Programa $model): bool { return $user->hasPermissionTo('gerenciar_programas'); }
}
