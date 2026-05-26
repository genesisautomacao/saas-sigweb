<?php
namespace App\Policies;
use App\Models\LoteValorHistorico;
use App\Models\User;

class LoteValorHistoricoPolicy
{
    public function viewAny(User $user): bool { return $user->can('view_lote_valor_historicos'); }
    public function view(User $user, LoteValorHistorico $model): bool { return $user->can('view_lote_valor_historicos'); }
    public function create(User $user): bool { return $user->can('create_lote_valor_historicos'); }
    public function update(User $user, LoteValorHistorico $model): bool { return $user->can('edit_lote_valor_historicos'); }
    public function delete(User $user, LoteValorHistorico $model): bool { return $user->can('delete_lote_valor_historicos'); }
}
