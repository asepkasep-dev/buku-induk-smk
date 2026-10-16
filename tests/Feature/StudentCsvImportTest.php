<?php

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function studentImportAdmin(): User
{
    test()->seed([RoleSeeder::class, PermissionSeeder::class]);

    return User::factory()->create([
        'role_id' => Role::query()->where('code', 'ADMIN')->firstOrFail()->id,
    ]);
}

test('admin can preview and import a validated CSV student list', function () {
    $admin = studentImportAdmin();
    $file = UploadedFile::fake()->createWithContent(
        'students.csv',
        "nis,full_name,gender,entry_year,status,phone\r\nCSV-001,Siswa CSV,P,2026,CALON,0812345\r\n"
    );

    Livewire::actingAs($admin)
        ->test('pages.students.import')
        ->set('csvFile', $file)
        ->call('previewImport')
        ->assertHasNoErrors()
        ->assertSee('1 baris siap')
        ->assertSee('Siswa CSV')
        ->call('import')
        ->assertHasNoErrors()
        ->assertRedirect(route('students.index'));

    $student = Student::query()->sole();
    expect($student->nis)->toBe('CSV-001')
        ->and($student->gender)->toBe('P')
        ->and($student->phone)->toBe('0812345');
    expect(AuditLog::query()->where('action', 'CREATE_STUDENT')->count())->toBe(1);
});

test('CSV import rejects duplicate identifiers without saving valid rows from the same file', function () {
    $admin = studentImportAdmin();
    $file = UploadedFile::fake()->createWithContent(
        'students.csv',
        "nis,full_name,gender,entry_year,status\r\nDUP-001,Siswa Satu,L,2026,CALON\r\nDUP-001,Siswa Dua,P,2026,CALON\r\n"
    );

    Livewire::actingAs($admin)
        ->test('pages.students.import')
        ->set('csvFile', $file)
        ->call('previewImport')
        ->assertSee('muncul lebih dari sekali')
        ->assertSee('belum bisa diimpor')
        ->call('import')
        ->assertHasErrors('csvFile');

    expect(Student::query()->count())->toBe(0);
});

test('student CSV template is available only to staff allowed to create student records', function () {
    $admin = studentImportAdmin();
    $this->actingAs($admin)
        ->get(route('students.import-template'))
        ->assertOk()
        ->assertDownload('template-impor-siswa.csv');
});

