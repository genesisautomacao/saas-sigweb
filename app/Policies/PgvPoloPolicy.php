<?php

namespace App\Policies;

use App\Models\PgvPolo;
use App\Models\User;

/** R83 (2026-10-12): quarteto no lugar de gerenciar_pgv_polos. temPermissao = falha fechada. */
class PgvPoloPolicy
{
    public function viewAny(User $user): bool { return $user->temPermissao('view_pgv_polos'); }
    public function view(User $user, PgvPolo $model): bool { return $user->temPermissao('view_pgv_polos'); }
    public function create(User $user): bool { return $user->temPermissao('create_pgv_polos'); }
    public function update(User $user, PgvPolo $model): bool { return $user->temPermissao('edit_pgv_polos'); }
    public function delete(User $user, PgvPolo $model): bool { return $user->temPermissao('delete_pgv_polos'); }
    public function deleteAny(User $user): bool { return $user->temPermissao('delete_pgv_polos'); }
    public function restore(User $user, PgvPolo $model): bool { return $user->temPermissao('delete_pgv_polos'); }
    public function forceDelete(User $user, PgvPolo $model): bool { return $user->temPermissao('delete_pgv_polos'); }
}
