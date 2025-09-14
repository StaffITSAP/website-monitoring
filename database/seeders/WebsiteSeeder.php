<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Website;

class WebsiteSeeder extends Seeder
{
    public function run(): void
    {
        $websites = [
            [
                'nama_website' => 'Website Premmiere',
                'url' => 'https://premmiere.co.id/',
                'aktif' => true,
                'interval_pemeriksaan' => 30,
            ],
            [
                'nama_website' => 'Website Dinatek',
                'url' => 'https://dinatek.co.id/',
                'aktif' => true,
                'interval_pemeriksaan' => 30,
            ],
            [
                'nama_website' => 'Website Hitech',
                'url' => 'https://hitechcomputer.co.id/',
                'aktif' => true,
                'interval_pemeriksaan' => 30,
            ],
            [
                'nama_website' => 'Website Strada',
                'url' => 'https://stradacoffee.com/',
                'aktif' => true,
                'interval_pemeriksaan' => 30,
            ],
        ];

        foreach ($websites as $website) {
            Website::create($website);
        }

        $this->command->info('Seeder website contoh berhasil ditambahkan!');
    }
}