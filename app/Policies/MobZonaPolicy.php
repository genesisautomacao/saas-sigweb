<?php

namespace App\Policies;

use App\Models\MobZona;
use App\Models\User;

/** R83 (2026-10-12): quarteto no lugar de gerenciar_mob_zonas. temPermissao = falha fechada (incidente 2026-08-05). */
class MobZonaPolicy
{
    public function viewAny(User $user): bool { return $user->temPermissao('view_mob_zonas'); }
    public function view(User $user, MobZona $model): bool { return $user->temPermissao('view_mob_zonas'); }
    public function create(User $user): bool { return $user->temPermissao('create_mob_zonas'); }
    public function update(User $user, MobZona $model): bool { return $user->temPermissao('edit_mob_zonas'); }
    public function delete(User $user, MobZona $model): bool { return $user->temPermissao('delete_mob_zonas'); }
    public function deleteAny(User $user): bool { return $user->temPermissao('delete_mob_zonas'); }
    public function restore(User $user, MobZona $model): bool { return $user->temPermissao('delete_mob_zonas'); }
    public function forceDelete(User $user, MobZona $model): bool { return $user->temPermissao('delete_mob_zonas'); }
}
