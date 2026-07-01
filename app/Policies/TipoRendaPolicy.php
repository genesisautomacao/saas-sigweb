<?php

namespace App\Policies;

use App\Models\{TipoRenda, User};

class TipoRendaPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('gerenciar_tipo_rendas'); }
    public function view(User $user, TipoRenda $model): bool { return $user->hasPermissionTo('gerenciar_tipo_rendas'); }
    public function create(User $user): bool { return $user->hasPermissionTo('gerenciar_tipo_rendas'); }
    public function update(User $user, TipoRenda $model): bool { return $user->hasPermissionTo('gerenciar_tipo_rendas'); }
    public function delete(User $user, TipoRenda $model): bool { return $user->hasPermissionTo('gerenciar_tipo_rendas'); }
}
