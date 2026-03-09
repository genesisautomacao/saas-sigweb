<?php
namespace App\Policies;
use App\Models\{LocalEstoque, User};

class LocalEstoquePolicy {
    public function viewAny(User $user): bool { return $user->hasPermissionTo('view_locais_estoque'); }
    public function view(User $user, LocalEstoque $model): bool { return $user->hasPermissionTo('view_locais_estoque'); }
    public function create(User $user): bool { return $user->hasPermissionTo('create_locais_estoque'); }
    public function update(User $user, LocalEstoque $model): bool { return $user->hasPermissionTo('edit_locais_estoque'); }
    public function delete(User $user, LocalEstoque $model): bool { return $user->hasPermissionTo('delete_locais_estoque'); }
}