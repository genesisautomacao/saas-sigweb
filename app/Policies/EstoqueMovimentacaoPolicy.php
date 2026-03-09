<?php
namespace App\Policies;
use App\Models\{EstoqueMovimentacao, User};

class EstoqueMovimentacaoPolicy {
    public function viewAny(User $user): bool { return $user->hasPermissionTo('view_movimentacoes'); }
    public function view(User $user, EstoqueMovimentacao $model): bool { return $user->hasPermissionTo('view_movimentacoes'); }
    public function create(User $user): bool { return $user->hasPermissionTo('create_movimentacoes'); }
    public function update(User $user, EstoqueMovimentacao $model): bool { return $user->hasPermissionTo('edit_movimentacoes'); }
    public function delete(User $user, EstoqueMovimentacao $model): bool { return $user->hasPermissionTo('delete_movimentacoes'); }
}