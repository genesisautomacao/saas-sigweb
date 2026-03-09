<?php
namespace App\Policies;
use App\Models\{Produto, User};

class ProdutoPolicy {
    public function viewAny(User $user): bool { return $user->hasPermissionTo('view_produtos'); }
    public function view(User $user, Produto $model): bool { return $user->hasPermissionTo('view_produtos'); }
    public function create(User $user): bool { return $user->hasPermissionTo('create_produtos'); }
    public function update(User $user, Produto $model): bool { return $user->hasPermissionTo('edit_produtos'); }
    public function delete(User $user, Produto $model): bool { return $user->hasPermissionTo('delete_produtos'); }
}