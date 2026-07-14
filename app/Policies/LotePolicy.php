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

    // Exclusão em lote + gestão da lixeira (SoftDeletes) — todas chaveadas por delete_lotes.
    // Master/Manager mantêm bypass via Gate::before (AppServiceProvider).
    public function deleteAny(User $user): bool { return $user->hasPermissionTo('delete_lotes'); }
    public function restore(User $user, Lote $lote): bool { return $user->hasPermissionTo('delete_lotes'); }
    public function restoreAny(User $user): bool { return $user->hasPermissionTo('delete_lotes'); }
    public function forceDelete(User $user, Lote $lote): bool { return $user->hasPermissionTo('delete_lotes'); }
    public function forceDeleteAny(User $user): bool { return $user->hasPermissionTo('delete_lotes'); }
}