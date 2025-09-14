<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'view_websites',
            'create_websites',
            'edit_websites',
            'delete_websites',
            'view_logs',
            'view_reports',
            'download_reports',
            'manage_users',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        $roleAdmin = Role::create(['name' => 'admin']);
        $roleAdmin->givePermissionTo(Permission::all());

        $roleOperator = Role::create(['name' => 'operator']);
        $roleOperator->givePermissionTo([
            'view_websites',
            'create_websites',
            'edit_websites',
            'view_logs',
            'view_reports',
            'download_reports',
        ]);

        $roleViewer = Role::create(['name' => 'viewer']);
        $roleViewer->givePermissionTo([
            'view_websites',
            'view_logs',
            'view_reports',
        ]);

        // Assign roles to users - dengan pengecekan jika user ada
        $adminUser = User::where('email', 'admin@monitoring.com')->first();
        $operatorUser = User::where('email', 'operator@monitoring.com')->first();
        $viewerUser = User::where('email', 'viewer@monitoring.com')->first();

        if ($adminUser) {
            $adminUser->assignRole('admin');
        }

        if ($operatorUser) {
            $operatorUser->assignRole('operator');
        }

        if ($viewerUser) {
            $viewerUser->assignRole('viewer');
        }

        $this->command->info('Role dan permission berhasil ditambahkan!');
    }
}