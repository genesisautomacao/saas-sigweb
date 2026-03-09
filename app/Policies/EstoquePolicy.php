<?php
namespace App\Policies;
use App\Models\{Estoque, User};

class EstoquePolicy {
    public function viewAny(User $user): bool { return $user->hasPermissionTo('view_estoques'); }
    public function view(User $user, Estoque $model): bool { return $user->hasPermissionTo('view_estoques'); }
    public function create(User $user): bool { return false; } // Saldo não se cria na mão
    public function update(User $user, Estoque $model): bool { return false; } // Saldo não se edita na mão
    public function delete(User $user, Estoque $model): bool { return false; } // Saldo não se deleta na mão
}