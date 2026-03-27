<?php
namespace App\Policies;
use App\Models\RuralPonte;
use App\Models\User;

class RuralPontePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('view_rural_pontes'); }
    public function view(User $user, RuralPonte $model): bool { return $user->hasPermissionTo('view_rural_pontes'); }
    public function create(User $user): bool { return $user->hasPermissionTo('create_rural_pontes'); }
    public function update(User $user, RuralPonte $model): bool { return $user->hasPermissionTo('edit_rural_pontes'); }
    public function delete(User $user, RuralPonte $model): bool { return $user->hasPermissionTo('delete_rural_pontes'); }
}