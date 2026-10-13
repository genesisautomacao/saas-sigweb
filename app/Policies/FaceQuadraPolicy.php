<?php

namespace App\Policies;

use App\Models\FaceQuadra;
use App\Models\User;

/** R83 (2026-10-12): quarteto no lugar de gerenciar_face_quadras. temPermissao = falha fechada. */
class FaceQuadraPolicy
{
    public function viewAny(User $user): bool { return $user->temPermissao('view_face_quadras'); }
    public function view(User $user, FaceQuadra $model): bool { return $user->temPermissao('view_face_quadras'); }
    public function create(User $user): bool { return $user->temPermissao('create_face_quadras'); }
    public function update(User $user, FaceQuadra $model): bool { return $user->temPermissao('edit_face_quadras'); }
    public function delete(User $user, FaceQuadra $model): bool { return $user->temPermissao('delete_face_quadras'); }
    public function deleteAny(User $user): bool { return $user->temPermissao('delete_face_quadras'); }
    public function restore(User $user, FaceQuadra $model): bool { return $user->temPermissao('delete_face_quadras'); }
    public function forceDelete(User $user, FaceQuadra $model): bool { return $user->temPermissao('delete_face_quadras'); }
}
