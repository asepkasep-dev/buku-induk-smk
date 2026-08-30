<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'code' => 'ADMIN',
                'name' => 'Admin / Waka Kurikulum',
                'description' => 'Mengelola sistem, data akademik, finalisasi, dan koreksi terkontrol.',
            ],
            [
                'code' => 'OPERATOR',
                'name' => 'Operator / TU',
                'description' => 'Mengelola data administrasi dan akademik sesuai scope yang diberikan.',
            ],
            [
                'code' => 'WALI_KELAS',
                'name' => 'Wali Kelas',
                'description' => 'Mengelola data akademik siswa pada rombel yang menjadi tanggung jawabnya.',
            ],
            [
                'code' => 'KEPALA_SEKOLAH',
                'name' => 'Kepala Sekolah',
                'description' => 'Melakukan verifikasi kelulusan tanpa mengubah data akademik.',
            ],
            [
                'code' => 'STUDENT',
                'name' => 'Siswa / Orang Tua',
                'description' => 'Melihat data milik siswa sendiri.',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['code' => $role['code']],
                $role
            );
        }
    }
}