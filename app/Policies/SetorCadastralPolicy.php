<?php

namespace App\Policies;

use App\Models\SetorCadastral;
use App\Models\User;

/** B9 (2026-10-19): setor da inscrição imobiliária. temPermissao = falha fechada. */
class SetorCadastralPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->temPermissao('view_setores_cadastrais');
    }

    public function view(User $user, SetorCadastral $model): bool
    {
        return $user->temPermissao('view_setores_cadastrais');
    }

    public function create(User $user): bool
    {
        return $user->temPermissao('create_setores_cadastrais');
    }

    public function update(User $user, SetorCadastral $model): bool
    {
        return $user->temPermissao('edit_setores_cadastrais');
    }

    public function delete(User $user, SetorCadastral $model): bool
    {
        return $user->temPermissao('delete_setores_cadastrais');
    }

    public function deleteAny(User $user): bool
    {
        return $user->temPermissao('delete_setores_cadastrais');
    }

    public function restore(User $user, SetorCadastral $model): bool
    {
        return $user->temPermissao('delete_setores_cadastrais');
    }

    public function restoreAny(User $user): bool
    {
        return $user->temPermissao('delete_setores_cadastrais');
    }

    public function forceDelete(User $user, SetorCadastral $model): bool
    {
        return $user->temPermissao('delete_setores_cadastrais');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->temPermissao('delete_setores_cadastrais');
    }
}
