<?php

namespace App\Policies;

use App\Models\MobCamera;
use App\Models\User;

/** R83 (2026-10-12): quarteto no lugar de gerenciar_mob_cameras. temPermissao = falha fechada (incidente 2026-08-05). */
class MobCameraPolicy
{
    public function viewAny(User $user): bool { return $user->temPermissao('view_mob_cameras'); }
    public function view(User $user, MobCamera $model): bool { return $user->temPermissao('view_mob_cameras'); }
    public function create(User $user): bool { return $user->temPermissao('create_mob_cameras'); }
    public function update(User $user, MobCamera $model): bool { return $user->temPermissao('edit_mob_cameras'); }
    public function delete(User $user, MobCamera $model): bool { return $user->temPermissao('delete_mob_cameras'); }
    public function deleteAny(User $user): bool { return $user->temPermissao('delete_mob_cameras'); }
    public function restore(User $user, MobCamera $model): bool { return $user->temPermissao('delete_mob_cameras'); }
    public function forceDelete(User $user, MobCamera $model): bool { return $user->temPermissao('delete_mob_cameras'); }
}
