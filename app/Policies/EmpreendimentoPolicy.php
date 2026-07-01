<?php

namespace App\Policies;

use App\Models\{Empreendimento, User};

class EmpreendimentoPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('gerenciar_empreendimentos'); }
    public function view(User $user, Empreendimento $model): bool { return $user->hasPermissionTo('gerenciar_empreendimentos'); }
    public function create(User $user): bool { return $user->hasPermissionTo('gerenciar_empreendimentos'); }
    public function update(User $user, Empreendimento $model): bool { return $user->hasPermissionTo('gerenciar_empreendimentos'); }
    public function delete(User $user, Empreendimento $model): bool { return $user->hasPermissionTo('gerenciar_empreendimentos'); }
}
