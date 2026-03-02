<?php

namespace App\Policies;

use App\Models\Seller;
use App\Models\User;

class SellerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_sellers');
    }

    public function view(User $user, Seller $model): bool
    {
        return $user->hasPermissionTo('view_sellers');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_sellers');
    }

    public function update(User $user, Seller $model): bool
    {
        return $user->hasPermissionTo('edit_sellers');
    }

    public function delete(User $user, Seller $model): bool
    {
        return $user->hasPermissionTo('delete_sellers');
    }
}