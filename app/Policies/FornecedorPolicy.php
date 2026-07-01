<?php

namespace App\Policies;

use App\Models\{Fornecedor, User};

class FornecedorPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('gerenciar_fornecedores'); }
    public function view(User $user, Fornecedor $model): bool { return $user->hasPermissionTo('gerenciar_fornecedores'); }
    public function create(User $user): bool { return $user->hasPermissionTo('gerenciar_fornecedores'); }
    public function update(User $user, Fornecedor $model): bool { return $user->hasPermissionTo('gerenciar_fornecedores'); }
    public function delete(User $user, Fornecedor $model): bool { return $user->hasPermissionTo('gerenciar_fornecedores'); }
}
