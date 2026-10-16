<?php

use App\Models\Guardian;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\StudentGuardian;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function studentPrivateRecordsFixture(): array
{
    test()->seed([RoleSeeder::class, PermissionSeeder::class]);

    $student = Student::query()->create([
        'nis' => 'PRIVATE-001',
        'full_name' => 'Siswa Uji Privasi',
        'gender' => 'L',
        'entry_year' => 2026,
        'status' => 'AKTIF',
    ]);
    $guardian = Guardian::query()->create([
        'full_name' => 'Nama Wali Rahasia',
        'phone' => '081234567890',
        'email' => 'wali-rahasia@example.test',
        'occupation' => 'Pekerjaan Rahasia',
    ]);
    StudentGuardian::query()->create([
        'student_id' => $student->id,
        'guardian_id' => $guardian->id,
        'relationship' => 'FATHER',
        'is_primary' => true,
    ]);

    return [$student, $guardian];
}

test('principal can view student but not guardian private details', function () {
    [$student] = studentPrivateRecordsFixture();
    $principal = User::factory()->create([
        'role_id' => Role::query()->where('code', 'KEPALA_SEKOLAH')->firstOrFail()->id,
    ]);
    $document = StudentDocument::query()->create([
        'student_id' => $student->id,
        'document_type' => 'Dokumen rahasia',
        'file_name' => 'dokumen-rahasia.pdf',
        'file_path' => "students/{$student->id}/documents/dokumen-rahasia.pdf",
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'status' => 'ACTIVE',
    ]);

    $this->actingAs($principal)
        ->get(route('students.show', $student))
        ->assertOk()
        ->assertSee('Identitas Siswa')
        ->assertDontSee('Nama Wali Rahasia')
        ->assertDontSee('wali-rahasia@example.test');

    $this->actingAs($principal)
        ->get(route('students.records', $student))
        ->assertOk()
        ->assertSee('Informasi orang tua dan wali memerlukan izin khusus.')
        ->assertDontSee('Nama Wali Rahasia')
        ->assertDontSee('wali-rahasia@example.test');

    $this->actingAs($principal)
        ->get(route('students.documents.download', [$student, $document]))
        ->assertForbidden();
});

test('admin with guardian permission can view guardian details', function () {
    [$student] = studentPrivateRecordsFixture();
    $admin = User::factory()->create([
        'role_id' => Role::query()->where('code', 'ADMIN')->firstOrFail()->id,
    ]);

    $this->actingAs($admin)
        ->get(route('students.show', $student))
        ->assertOk()
        ->assertSee('Nama Wali Rahasia')
        ->assertSee('081234567890');
});

test('authenticated root opens the role-scoped dashboard', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $admin = User::factory()->create([
        'role_id' => Role::query()->where('code', 'ADMIN')->firstOrFail()->id,
    ]);

    $this->actingAs($admin)
        ->get('/')
        ->assertRedirect(route('dashboard'));

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Periode akademik aktif')
        ->assertSee('Ringkasan siswa dalam cakupan akses Anda')
        ->assertSee('Semua siswa');
});
