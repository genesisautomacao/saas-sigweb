<?php

namespace App\Policies;

use App\Models\{OperacaoInterna, User};

class OperacaoInternaPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('gerenciar_operacao_internas'); }
    public function view(User $user, OperacaoInterna $model): bool { return $user->hasPermissionTo('gerenciar_operacao_internas'); }
    public function create(User $user): bool { return $user->hasPermissionTo('gerenciar_operacao_internas'); }
    public function update(User $user, OperacaoInterna $model): bool { return $user->hasPermissionTo('gerenciar_operacao_internas'); }
    public function delete(User $user, OperacaoInterna $model): bool { return $user->hasPermissionTo('gerenciar_operacao_internas'); }
}
