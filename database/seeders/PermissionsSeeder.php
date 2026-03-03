<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Lista das ações que existem dentro do seu sistema
        $permissions = [

            /* users */
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',

            /* roles */
            'view_roles',
            'create_roles',
            'edit_roles',
            'delete_roles',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }
}