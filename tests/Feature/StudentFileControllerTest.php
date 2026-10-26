<?php

use App\Models\Role;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    Storage::fake('local');
});

test('admin can download student import excel template', function () {
    $adminRole = Role::where('code', 'ADMIN')->firstOrFail();
    $admin = User::create([
        'name' => 'Admin Test',
        'email' => 'admin.template@test.local',
        'password' => bcrypt('password'),
        'role_id' => $adminRole->id,
        'status' => 'ACTIVE',
    ]);

    $response = $this->actingAs($admin)->get(route('students.import-template'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('non-admin user is forbidden to download student import template', function () {
    $studentRole = Role::where('code', 'STUDENT')->firstOrFail();
    $user = User::create([
        'name' => 'Siswa Test',
        'email' => 'siswa.template@test.local',
        'password' => bcrypt('password'),
        'role_id' => $studentRole->id,
        'status' => 'ACTIVE',
    ]);

    $response = $this->actingAs($user)->get(route('students.import-template'));
    $response->assertForbidden();
});

test('authorized user can download student document', function () {
    $adminRole = Role::where('code', 'ADMIN')->firstOrFail();
    $admin = User::create([
        'name' => 'Admin Document Test',
        'email' => 'admin.doc@test.local',
        'password' => bcrypt('password'),
        'role_id' => $adminRole->id,
        'status' => 'ACTIVE',
    ]);

    $student = Student::create([
        'nis' => 'DOC-001',
        'full_name' => 'Siswa Dokumen',
        'gender' => 'L',
        'entry_year' => 2026,
        'status' => 'AKTIF',
    ]);

    $path = 'students/documents/sample.pdf';
    Storage::disk('local')->put($path, 'sample file content');

    $doc = StudentDocument::create([
        'student_id' => $student->id,
        'document_type' => 'IJAZAH_SMP',
        'file_name' => 'ijazah.pdf',
        'file_path' => $path,
        'file_size' => 19,
        'mime_type' => 'application/pdf',
        'uploaded_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->get(route('students.documents.download', [
        'student' => $student->id,
        'document' => $doc->id,
    ]));

    $response->assertOk();
});

test('downloading mismatched student document returns 404', function () {
    $adminRole = Role::where('code', 'ADMIN')->firstOrFail();
    $admin = User::create([
        'name' => 'Admin Test 2',
        'email' => 'admin.doc2@test.local',
        'password' => bcrypt('password'),
        'role_id' => $adminRole->id,
        'status' => 'ACTIVE',
    ]);

    $student1 = Student::create([
        'nis' => 'DOC-002',
        'full_name' => 'Siswa Dokumen 2',
        'gender' => 'L',
        'entry_year' => 2026,
        'status' => 'AKTIF',
    ]);

    $student2 = Student::create([
        'nis' => 'DOC-003',
        'full_name' => 'Siswa Dokumen 3',
        'gender' => 'P',
        'entry_year' => 2026,
        'status' => 'AKTIF',
    ]);

    $path = 'students/documents/sample2.pdf';
    Storage::disk('local')->put($path, 'dummy content');

    $doc = StudentDocument::create([
        'student_id' => $student1->id,
        'document_type' => 'AKTA_KELAHIRAN',
        'file_name' => 'akta.pdf',
        'file_path' => $path,
        'file_size' => 13,
        'mime_type' => 'application/pdf',
        'uploaded_by' => $admin->id,
    ]);

    // Download document milik student1 via URL student2 harus 404
    $response = $this->actingAs($admin)->get(route('students.documents.download', [
        'student' => $student2->id,
        'document' => $doc->id,
    ]));

    $response->assertNotFound();
});
