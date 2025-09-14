<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Buat user administrator
        User::create([
            'name' => 'Staff IT',
            'email' => 'it@premmiere.co.id',
            'password' => Hash::make('St@ff1t#'),
            'email_verified_at' => now(),
        ]);

        // Buat user operator
        User::create([
            'name' => 'Operator Monitoring',
            'email' => 'operator@monitoring.com',
            'password' => Hash::make('operator123'),
            'email_verified_at' => now(),
        ]);

        // Buat user viewer
        User::create([
            'name' => 'Viewer Laporan',
            'email' => 'viewer@monitoring.com',
            'password' => Hash::make('viewer123'),
            'email_verified_at' => now(),
        ]);

        $this->command->info('Seeder user berhasil ditambahkan!');
        $this->command->info('Email: admin@monitoring.com | Password: password123');
        $this->command->info('Email: operator@monitoring.com | Password: operator123');
        $this->command->info('Email: viewer@monitoring.com | Password: viewer123');
    }
}