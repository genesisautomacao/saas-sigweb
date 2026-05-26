<?php
namespace App\Policies;
use App\Models\ParametroUrbano;
use App\Models\User;

class ParametroUrbanoPolicy
{
    public function viewAny(User $user): bool { return $user->can('view_parametros_urbanos'); }
    public function view(User $user, ParametroUrbano $model): bool { return $user->can('view_parametros_urbanos'); }
    public function create(User $user): bool { return $user->can('create_parametros_urbanos'); }
    public function update(User $user, ParametroUrbano $model): bool { return $user->can('edit_parametros_urbanos'); }
    public function delete(User $user, ParametroUrbano $model): bool { return $user->can('delete_parametros_urbanos'); }
}
