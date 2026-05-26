<?php
namespace App\Policies;
use App\Models\SetorFiscal;
use App\Models\User;

class SetorFiscalPolicy
{
    public function viewAny(User $user): bool { return $user->can('view_setores_fiscais'); }
    public function view(User $user, SetorFiscal $model): bool { return $user->can('view_setores_fiscais'); }
    public function create(User $user): bool { return $user->can('create_setores_fiscais'); }
    public function update(User $user, SetorFiscal $model): bool { return $user->can('edit_setores_fiscais'); }
    public function delete(User $user, SetorFiscal $model): bool { return $user->can('delete_setores_fiscais'); }
}
