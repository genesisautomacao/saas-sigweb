<?php

namespace App\Policies;

use App\Models\{FaceQuadra, User};

class FaceQuadraPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('gerenciar_face_quadras'); }
    public function view(User $user, FaceQuadra $model): bool { return $user->hasPermissionTo('gerenciar_face_quadras'); }
    public function create(User $user): bool { return $user->hasPermissionTo('gerenciar_face_quadras'); }
    public function update(User $user, FaceQuadra $model): bool { return $user->hasPermissionTo('gerenciar_face_quadras'); }
    public function delete(User $user, FaceQuadra $model): bool { return $user->hasPermissionTo('gerenciar_face_quadras'); }
}
