<?php
namespace App\Policies;
use App\Models\RuralEstrada;
use App\Models\User;

class RuralEstradaPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('view_rural_estradas'); }
    public function view(User $user, RuralEstrada $model): bool { return $user->hasPermissionTo('view_rural_estradas'); }
    public function create(User $user): bool { return $user->hasPermissionTo('create_rural_estradas'); }
    public function update(User $user, RuralEstrada $model): bool { return $user->hasPermissionTo('edit_rural_estradas'); }
    public function delete(User $user, RuralEstrada $model): bool { return $user->hasPermissionTo('delete_rural_estradas'); }
}