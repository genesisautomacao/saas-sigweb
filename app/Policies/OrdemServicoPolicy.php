<?php
namespace App\Policies;
use App\Models\{OrdemServico, User};

class OrdemServicoPolicy {
    public function viewAny(User $user): bool { return $user->hasPermissionTo('view_ordens_servico'); }
    public function view(User $user, OrdemServico $model): bool { return $user->hasPermissionTo('view_ordens_servico'); }
    public function create(User $user): bool { return $user->hasPermissionTo('create_ordens_servico'); }
    public function update(User $user, OrdemServico $model): bool { return $user->hasPermissionTo('edit_ordens_servico'); }
    public function delete(User $user, OrdemServico $model): bool { return $user->hasPermissionTo('delete_ordens_servico'); }
}