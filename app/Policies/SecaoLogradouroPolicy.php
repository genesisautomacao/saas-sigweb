<?php

namespace App\Policies;

use App\Models\SecaoLogradouro;
use App\Models\User;

/** R83 (2026-10-12): quarteto no lugar de gerenciar_secoes_logradouro. temPermissao = falha fechada. */
class SecaoLogradouroPolicy
{
    public function viewAny(User $user): bool { return $user->temPermissao('view_secoes_logradouro'); }
    public function view(User $user, SecaoLogradouro $model): bool { return $user->temPermissao('view_secoes_logradouro'); }
    public function create(User $user): bool { return $user->temPermissao('create_secoes_logradouro'); }
    public function update(User $user, SecaoLogradouro $model): bool { return $user->temPermissao('edit_secoes_logradouro'); }
    public function delete(User $user, SecaoLogradouro $model): bool { return $user->temPermissao('delete_secoes_logradouro'); }
    public function deleteAny(User $user): bool { return $user->temPermissao('delete_secoes_logradouro'); }
    public function restore(User $user, SecaoLogradouro $model): bool { return $user->temPermissao('delete_secoes_logradouro'); }
    public function forceDelete(User $user, SecaoLogradouro $model): bool { return $user->temPermissao('delete_secoes_logradouro'); }
}
