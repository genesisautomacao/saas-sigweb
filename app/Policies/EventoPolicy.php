<?php

namespace App\Policies;

use App\Models\{Evento, User};

class EventoPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('gerenciar_eventos'); }
    public function view(User $user, Evento $model): bool { return $user->hasPermissionTo('gerenciar_eventos'); }
    public function create(User $user): bool { return $user->hasPermissionTo('gerenciar_eventos'); }
    public function update(User $user, Evento $model): bool { return $user->hasPermissionTo('gerenciar_eventos'); }
    public function delete(User $user, Evento $model): bool { return $user->hasPermissionTo('gerenciar_eventos'); }
}
