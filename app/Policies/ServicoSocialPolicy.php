<?php

namespace App\Policies;

use App\Models\{ServicoSocial, User};

class ServicoSocialPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('gerenciar_servico_sociais'); }
    public function view(User $user, ServicoSocial $model): bool { return $user->hasPermissionTo('gerenciar_servico_sociais'); }
    public function create(User $user): bool { return $user->hasPermissionTo('gerenciar_servico_sociais'); }
    public function update(User $user, ServicoSocial $model): bool { return $user->hasPermissionTo('gerenciar_servico_sociais'); }
    public function delete(User $user, ServicoSocial $model): bool { return $user->hasPermissionTo('gerenciar_servico_sociais'); }
}
