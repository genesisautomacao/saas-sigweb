<?php

namespace App\Policies;

use App\Models\{Estabelecimento, User};

class EstabelecimentoPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('gerenciar_estabelecimentos'); }
    public function view(User $user, Estabelecimento $model): bool { return $user->hasPermissionTo('gerenciar_estabelecimentos'); }
    public function create(User $user): bool { return $user->hasPermissionTo('gerenciar_estabelecimentos'); }
    public function update(User $user, Estabelecimento $model): bool { return $user->hasPermissionTo('gerenciar_estabelecimentos'); }
    public function delete(User $user, Estabelecimento $model): bool { return $user->hasPermissionTo('gerenciar_estabelecimentos'); }
}
