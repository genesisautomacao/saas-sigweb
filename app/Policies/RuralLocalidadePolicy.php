<?php
namespace App\Policies;
use App\Models\RuralLocalidade;
use App\Models\User;

class RuralLocalidadePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('view_rural_localidades'); }
    public function view(User $user, RuralLocalidade $model): bool { return $user->hasPermissionTo('view_rural_localidades'); }
    public function create(User $user): bool { return $user->hasPermissionTo('create_rural_localidades'); }
    public function update(User $user, RuralLocalidade $model): bool { return $user->hasPermissionTo('edit_rural_localidades'); }
    public function delete(User $user, RuralLocalidade $model): bool { return $user->hasPermissionTo('delete_rural_localidades'); }
}