<?php

namespace App\Policies;

use App\Models\MobVia;
use App\Models\User;

/** R83 (2026-10-12): quarteto no lugar de gerenciar_mob_vias. temPermissao = falha fechada (incidente 2026-08-05). */
class MobViaPolicy
{
    public function viewAny(User $user): bool { return $user->temPermissao('view_mob_vias'); }
    public function view(User $user, MobVia $model): bool { return $user->temPermissao('view_mob_vias'); }
    public function create(User $user): bool { return $user->temPermissao('create_mob_vias'); }
    public function update(User $user, MobVia $model): bool { return $user->temPermissao('edit_mob_vias'); }
    public function delete(User $user, MobVia $model): bool { return $user->temPermissao('delete_mob_vias'); }
    public function deleteAny(User $user): bool { return $user->temPermissao('delete_mob_vias'); }
    public function restore(User $user, MobVia $model): bool { return $user->temPermissao('delete_mob_vias'); }
    public function forceDelete(User $user, MobVia $model): bool { return $user->temPermissao('delete_mob_vias'); }
}
