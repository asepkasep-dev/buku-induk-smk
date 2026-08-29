<?php

namespace Database\Seeders;

use App\Models\Competency;
use Illuminate\Database\Seeder;

class CompetencySeeder extends Seeder
{
    public function run(): void
    {
        Competency::updateOrCreate(
            ['code' => 'MPLB'],
            [
                'name' => 'Manajemen Perkantoran dan Layanan Bisnis',
                'short_name' => 'MPLB',
                'is_active' => true,
            ]
        );

        Competency::updateOrCreate(
            ['code' => 'FARMASI'],
            [
                'name' => 'Farmasi',
                'short_name' => 'Farmasi',
                'is_active' => true,
            ]
        );

        Competency::updateOrCreate(
            ['code' => 'RPL'],
            [
                'name' => 'Rekayasa Perangkat Lunak',
                'short_name' => 'RPL',
                'is_active' => true,
            ]
        );
    }
}