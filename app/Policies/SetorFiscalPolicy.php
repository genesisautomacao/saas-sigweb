<?php

namespace App\Policies;

use App\Models\SetorFiscal;
use App\Models\User;

/** B9 (2026-10-19): setor da inscrição imobiliária. temPermissao = falha fechada. */
class SetorFiscalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->temPermissao('view_setores_fiscais');
    }

    public function view(User $user, SetorFiscal $model): bool
    {
        return $user->temPermissao('view_setores_fiscais');
    }

    public function create(User $user): bool
    {
        return $user->temPermissao('create_setores_fiscais');
    }

    public function update(User $user, SetorFiscal $model): bool
    {
        return $user->temPermissao('edit_setores_fiscais');
    }

    public function delete(User $user, SetorFiscal $model): bool
    {
        return $user->temPermissao('delete_setores_fiscais');
    }

    public function deleteAny(User $user): bool
    {
        return $user->temPermissao('delete_setores_fiscais');
    }

    public function restore(User $user, SetorFiscal $model): bool
    {
        return $user->temPermissao('delete_setores_fiscais');
    }

    public function restoreAny(User $user): bool
    {
        return $user->temPermissao('delete_setores_fiscais');
    }

    public function forceDelete(User $user, SetorFiscal $model): bool
    {
        return $user->temPermissao('delete_setores_fiscais');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->temPermissao('delete_setores_fiscais');
    }
}
