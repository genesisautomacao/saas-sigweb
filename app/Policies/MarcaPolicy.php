<?php
namespace App\Policies;
use App\Models\{Marca, User};

class MarcaPolicy {
    public function viewAny(User $user): bool { return $user->hasPermissionTo('view_marcas'); }
    public function view(User $user, Marca $model): bool { return $user->hasPermissionTo('view_marcas'); }
    public function create(User $user): bool { return $user->hasPermissionTo('create_marcas'); }
    public function update(User $user, Marca $model): bool { return $user->hasPermissionTo('edit_marcas'); }
    public function delete(User $user, Marca $model): bool { return $user->hasPermissionTo('delete_marcas'); }
}