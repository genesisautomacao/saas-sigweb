<?php
namespace App\Policies;
use App\Models\Lote;
use App\Models\User;

class LotePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('view_lotes'); }
    public function view(User $user, Lote $lote): bool { return $user->hasPermissionTo('view_lotes'); }
    public function create(User $user): bool { return $user->hasPermissionTo('create_lotes'); }
    public function update(User $user, Lote $lote): bool { return $user->hasPermissionTo('edit_lotes'); }
    public function delete(User $user, Lote $lote): bool { return $user->hasPermissionTo('delete_lotes'); }
}