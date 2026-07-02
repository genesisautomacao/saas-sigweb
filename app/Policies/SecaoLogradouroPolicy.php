<?php

namespace App\Policies;

use App\Models\{SecaoLogradouro, User};

class SecaoLogradouroPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('gerenciar_secoes_logradouro'); }
    public function view(User $user, SecaoLogradouro $model): bool { return $user->hasPermissionTo('gerenciar_secoes_logradouro'); }
    public function create(User $user): bool { return $user->hasPermissionTo('gerenciar_secoes_logradouro'); }
    public function update(User $user, SecaoLogradouro $model): bool { return $user->hasPermissionTo('gerenciar_secoes_logradouro'); }
    public function delete(User $user, SecaoLogradouro $model): bool { return $user->hasPermissionTo('gerenciar_secoes_logradouro'); }
}
