<?php

namespace App\Policies;

use App\Models\PgvAmostra;
use App\Models\User;

/** R83 (2026-10-12): quarteto no lugar de gerenciar_pgv_amostras. temPermissao = falha fechada. */
class PgvAmostraPolicy
{
    public function viewAny(User $user): bool { return $user->temPermissao('view_pgv_amostras'); }
    public function view(User $user, PgvAmostra $model): bool { return $user->temPermissao('view_pgv_amostras'); }
    public function create(User $user): bool { return $user->temPermissao('create_pgv_amostras'); }
    public function update(User $user, PgvAmostra $model): bool { return $user->temPermissao('edit_pgv_amostras'); }
    public function delete(User $user, PgvAmostra $model): bool { return $user->temPermissao('delete_pgv_amostras'); }
    public function deleteAny(User $user): bool { return $user->temPermissao('delete_pgv_amostras'); }
    public function restore(User $user, PgvAmostra $model): bool { return $user->temPermissao('delete_pgv_amostras'); }
    public function forceDelete(User $user, PgvAmostra $model): bool { return $user->temPermissao('delete_pgv_amostras'); }
}
