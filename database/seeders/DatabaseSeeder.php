<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = Role::findOrCreate('admin', 'web');
        Role::findOrCreate('user', 'web');

        $admin->syncPermissions([
            Permission::findOrCreate('manage-users', 'web'),
            Permission::findOrCreate('manage-roles', 'web'),
        ]);
    }
}
