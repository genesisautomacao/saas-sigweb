<?php

namespace App\Policies;

use App\Models\{LoteEstoque, User};

class LoteEstoquePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('gerenciar_lote_estoques'); }
    public function view(User $user, LoteEstoque $model): bool { return $user->hasPermissionTo('gerenciar_lote_estoques'); }
    public function create(User $user): bool { return $user->hasPermissionTo('gerenciar_lote_estoques'); }
    public function update(User $user, LoteEstoque $model): bool { return $user->hasPermissionTo('gerenciar_lote_estoques'); }
    public function delete(User $user, LoteEstoque $model): bool { return $user->hasPermissionTo('gerenciar_lote_estoques'); }
}
