<?php

namespace App\Policies;

use App\Models\{InformacaoSocial, User};

class InformacaoSocialPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('gerenciar_informacao_sociais'); }
    public function view(User $user, InformacaoSocial $model): bool { return $user->hasPermissionTo('gerenciar_informacao_sociais'); }
    public function create(User $user): bool { return $user->hasPermissionTo('gerenciar_informacao_sociais'); }
    public function update(User $user, InformacaoSocial $model): bool { return $user->hasPermissionTo('gerenciar_informacao_sociais'); }
    public function delete(User $user, InformacaoSocial $model): bool { return $user->hasPermissionTo('gerenciar_informacao_sociais'); }
}
