<?php
namespace App\Policies;
use App\Models\RuralPontoInteresse;
use App\Models\User;

class RuralPontoInteressePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('view_rural_pontos_interesse'); }
    public function view(User $user, RuralPontoInteresse $model): bool { return $user->hasPermissionTo('view_rural_pontos_interesse'); }
    public function create(User $user): bool { return $user->hasPermissionTo('create_rural_pontos_interesse'); }
    public function update(User $user, RuralPontoInteresse $model): bool { return $user->hasPermissionTo('edit_rural_pontos_interesse'); }
    public function delete(User $user, RuralPontoInteresse $model): bool { return $user->hasPermissionTo('delete_rural_pontos_interesse'); }
}