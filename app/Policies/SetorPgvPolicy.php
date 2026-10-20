<?php
namespace App\Policies;
use App\Models\SetorPgv;
use App\Models\User;

class SetorPgvPolicy
{
    public function viewAny(User $user): bool { return $user->can('view_setores_pgv'); }
    public function view(User $user, SetorPgv $model): bool { return $user->can('view_setores_pgv'); }
    public function create(User $user): bool { return $user->can('create_setores_pgv'); }
    public function update(User $user, SetorPgv $model): bool { return $user->can('edit_setores_pgv'); }
    public function delete(User $user, SetorPgv $model): bool { return $user->can('delete_setores_pgv'); }
}
