<?php

namespace App\Policies;

use App\Models\AreaReurb;
use App\Models\User;

/** R83 (2026-10-12): quarteto no lugar de gerenciar_areas_reurb. temPermissao = falha fechada. */
class AreaReurbPolicy
{
    public function viewAny(User $user): bool { return $user->temPermissao('view_areas_reurb'); }
    public function view(User $user, AreaReurb $model): bool { return $user->temPermissao('view_areas_reurb'); }
    public function create(User $user): bool { return $user->temPermissao('create_areas_reurb'); }
    public function update(User $user, AreaReurb $model): bool { return $user->temPermissao('edit_areas_reurb'); }
    public function delete(User $user, AreaReurb $model): bool { return $user->temPermissao('delete_areas_reurb'); }
    public function deleteAny(User $user): bool { return $user->temPermissao('delete_areas_reurb'); }
    public function restore(User $user, AreaReurb $model): bool { return $user->temPermissao('delete_areas_reurb'); }
    public function forceDelete(User $user, AreaReurb $model): bool { return $user->temPermissao('delete_areas_reurb'); }
}
