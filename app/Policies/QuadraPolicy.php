<?php

namespace App\Policies;

use App\Models\Quadra;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class QuadraPolicy
{
  public function viewAny(User $user): bool { return $user->hasPermissionTo('view_quadras'); }
    public function view(User $user, Quadra $quadra): bool { return $user->hasPermissionTo('view_quadras'); }
    // Como é Read-Only, você pode retornar false nas de criação/edição se quiser travar na raiz:
    public function create(User $user): bool { return false; }
    public function update(User $user, Quadra $quadra): bool { return false; }
    public function delete(User $user, Quadra $quadra): bool { return false; }
}
