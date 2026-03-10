<?php
namespace App\Policies;
use App\Models\{SolicitacaoManutencao, User};

class SolicitacaoManutencaoPolicy {
    public function viewAny(User $user): bool { return $user->hasPermissionTo('view_solicitacoes'); }
    public function view(User $user, SolicitacaoManutencao $model): bool { return $user->hasPermissionTo('view_solicitacoes'); }
    public function create(User $user): bool { return $user->hasPermissionTo('create_solicitacoes'); }
    public function update(User $user, SolicitacaoManutencao $model): bool { return $user->hasPermissionTo('edit_solicitacoes'); }
    public function delete(User $user, SolicitacaoManutencao $model): bool { return $user->hasPermissionTo('delete_solicitacoes'); }
}