<?php
namespace App\Policies;
use App\Models\RuralHidrografia;
use App\Models\User;

class RuralHidrografiaPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('view_rural_hidrografias'); }
    public function view(User $user, RuralHidrografia $model): bool { return $user->hasPermissionTo('view_rural_hidrografias'); }
    public function create(User $user): bool { return $user->hasPermissionTo('create_rural_hidrografias'); }
    public function update(User $user, RuralHidrografia $model): bool { return $user->hasPermissionTo('edit_rural_hidrografias'); }
    public function delete(User $user, RuralHidrografia $model): bool { return $user->hasPermissionTo('delete_rural_hidrografias'); }
}