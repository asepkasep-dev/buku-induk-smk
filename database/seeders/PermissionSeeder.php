<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['code' => 'VIEW_STUDENT', 'name' => 'Lihat Data Siswa'],
            ['code' => 'MANAGE_STUDENT', 'name' => 'Kelola Data Siswa'],
            ['code' => 'VIEW_REPORT_SCORE', 'name' => 'Lihat Nilai Rapor'],
            ['code' => 'INPUT_REPORT_SCORE', 'name' => 'Input Nilai Rapor'],
            ['code' => 'LOCK_REPORT_SCORE', 'name' => 'Kunci Nilai Rapor'],
            ['code' => 'FINALIZE_REPORT_SCORE', 'name' => 'Finalisasi Nilai Rapor'],
            ['code' => 'MANAGE_ATTENDANCE', 'name' => 'Kelola Kehadiran'],
            ['code' => 'MANAGE_EXTRACURRICULAR', 'name' => 'Kelola Ekstrakurikuler'],
            ['code' => 'MANAGE_PKL', 'name' => 'Kelola PKL'],
            ['code' => 'MANAGE_UKK', 'name' => 'Kelola UKK'],
            ['code' => 'VERIFY_GRADUATION', 'name' => 'Verifikasi Kelulusan'],
            ['code' => 'FINALIZE_GRADUATION', 'name' => 'Finalisasi Kelulusan'],
            ['code' => 'CORRECT_FINALIZED_REPORT_SCORE', 'name' => 'Koreksi Nilai Rapor Final'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['code' => $permission['code']],
                $permission
            );
        }

        $allPermissionIds = Permission::pluck('id')->all();

        Role::where('code', 'ADMIN')
            ->firstOrFail()
            ->permissions()
            ->sync($allPermissionIds);

        $operatorPermissions = Permission::whereIn('code', [
            'VIEW_STUDENT',
            'MANAGE_STUDENT',
            'VIEW_REPORT_SCORE',
            'INPUT_REPORT_SCORE',
            'LOCK_REPORT_SCORE',
            'MANAGE_ATTENDANCE',
            'MANAGE_EXTRACURRICULAR',
            'MANAGE_PKL',
            'MANAGE_UKK',
        ])->pluck('id')->all();

        Role::where('code', 'OPERATOR')
            ->firstOrFail()
            ->permissions()
            ->sync($operatorPermissions);

        $waliKelasPermissions = Permission::whereIn('code', [
            'VIEW_STUDENT',
            'VIEW_REPORT_SCORE',
            'INPUT_REPORT_SCORE',
            'LOCK_REPORT_SCORE',
            'MANAGE_ATTENDANCE',
            'MANAGE_EXTRACURRICULAR',
        ])->pluck('id')->all();

        Role::where('code', 'WALI_KELAS')
            ->firstOrFail()
            ->permissions()
            ->sync($waliKelasPermissions);

        $kepalaSekolahPermissions = Permission::whereIn('code', [
            'VIEW_STUDENT',
            'VIEW_REPORT_SCORE',
            'VERIFY_GRADUATION',
        ])->pluck('id')->all();

        Role::where('code', 'KEPALA_SEKOLAH')
            ->firstOrFail()
            ->permissions()
            ->sync($kepalaSekolahPermissions);

        $studentPermissions = Permission::whereIn('code', [
            'VIEW_STUDENT',
            'VIEW_REPORT_SCORE',
        ])->pluck('id')->all();

        Role::where('code', 'STUDENT')
            ->firstOrFail()
            ->permissions()
            ->sync($studentPermissions);
    }
}