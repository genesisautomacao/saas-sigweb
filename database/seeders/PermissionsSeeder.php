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

            /* leads */
            'view_leads',
            'create_leads',
            'edit_leads',
            'delete_leads',
            'view_my_leads',
            'import_leads',

            /* sellers (vendedores) */
            'view_sellers',
            'create_sellers',
            'edit_sellers',
            'delete_sellers',

            /* crm settings (status, origin, potential) */
            'manage_crm_settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }
}