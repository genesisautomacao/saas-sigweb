<?php
namespace App\Policies;
use App\Models\PgvParametro;
use App\Models\User;

class PgvParametroPolicy
{
    public function viewAny(User $user): bool { return $user->can('view_pgv_parametros'); }
    public function view(User $user, PgvParametro $model): bool { return $user->can('view_pgv_parametros'); }
    public function create(User $user): bool { return $user->can('create_pgv_parametros'); }
    public function update(User $user, PgvParametro $model): bool { return $user->can('edit_pgv_parametros'); }
    public function delete(User $user, PgvParametro $model): bool { return $user->can('delete_pgv_parametros'); }
}
