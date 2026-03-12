<?php
namespace App\Policies;
use App\Models\Logradouro;
use App\Models\User;

class LogradouroPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('view_logradouros'); }
    public function view(User $user, Logradouro $logradouro): bool { return $user->hasPermissionTo('view_logradouros'); }
    public function create(User $user): bool { return $user->hasPermissionTo('create_logradouros'); }
    public function update(User $user, Logradouro $logradouro): bool { return $user->hasPermissionTo('edit_logradouros'); }
    public function delete(User $user, Logradouro $logradouro): bool { return $user->hasPermissionTo('delete_logradouros'); }
}