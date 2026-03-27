<?php
namespace App\Policies;
use App\Models\RuralPropriedade;
use App\Models\User;

class RuralPropriedadePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('view_rural_propriedades'); }
    public function view(User $user, RuralPropriedade $model): bool { return $user->hasPermissionTo('view_rural_propriedades'); }
    public function create(User $user): bool { return $user->hasPermissionTo('create_rural_propriedades'); }
    public function update(User $user, RuralPropriedade $model): bool { return $user->hasPermissionTo('edit_rural_propriedades'); }
    public function delete(User $user, RuralPropriedade $model): bool { return $user->hasPermissionTo('delete_rural_propriedades'); }
}