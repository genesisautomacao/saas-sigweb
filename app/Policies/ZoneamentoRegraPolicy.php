<?php
namespace App\Policies;
use App\Models\ZoneamentoRegra;
use App\Models\User;

class ZoneamentoRegraPolicy
{
    public function viewAny(User $user): bool { return $user->can('view_regras_zoneamento'); }
    public function view(User $user, ZoneamentoRegra $model): bool { return $user->can('view_regras_zoneamento'); }
    public function create(User $user): bool { return $user->can('create_regras_zoneamento'); }
    public function update(User $user, ZoneamentoRegra $model): bool { return $user->can('edit_regras_zoneamento'); }
    public function delete(User $user, ZoneamentoRegra $model): bool { return $user->can('delete_regras_zoneamento'); }
}
