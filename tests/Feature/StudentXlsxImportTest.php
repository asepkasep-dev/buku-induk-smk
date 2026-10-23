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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

function studentImportAdmin(): User
{
    test()->seed([RoleSeeder::class, PermissionSeeder::class]);

    return User::factory()->create([
        'role_id' => Role::query()->where('code', 'ADMIN')->firstOrFail()->id,
    ]);
}

function xlsxUpload(string $name, array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');

    $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);
    $contents = file_get_contents($path);
    @unlink($path);

    return UploadedFile::fake()->createWithContent($name, $contents);
}

test('admin can preview and import a validated XLSX student list', function () {
    $admin = studentImportAdmin();
    $file = xlsxUpload('students.xlsx', [
        ['nis', 'full_name', 'gender', 'entry_year', 'status', 'phone'],
        ['XLSX-001', 'Siswa XLSX', 'P', 2026, 'CALON', '0812345'],
    ]);

    Livewire::actingAs($admin)
        ->test('pages.students.import')
        ->set('importFile', $file)
        ->call('previewImport')
        ->assertHasNoErrors()
        ->assertSee('1 baris siap')
        ->assertSee('Siswa XLSX')
        ->call('import')
        ->assertHasNoErrors()
        ->assertRedirect(route('students.index'));

    $student = Student::query()->sole();
    expect($student->nis)->toBe('XLSX-001')
        ->and($student->gender)->toBe('P')
        ->and($student->phone)->toBe('0812345');
    expect(AuditLog::query()->where('action', 'CREATE_STUDENT')->count())->toBe(1);
});

test('XLSX import rejects duplicate identifiers without saving valid rows from the same file', function () {
    $admin = studentImportAdmin();
    $file = xlsxUpload('students.xlsx', [
        ['nis', 'full_name', 'gender', 'entry_year', 'status'],
        ['DUP-001', 'Siswa Satu', 'L', 2026, 'CALON'],
        ['DUP-001', 'Siswa Dua', 'P', 2026, 'CALON'],
    ]);

    Livewire::actingAs($admin)
        ->test('pages.students.import')
        ->set('importFile', $file)
        ->call('previewImport')
        ->assertSee('muncul lebih dari sekali')
        ->assertSee('belum bisa diimpor')
        ->call('import')
        ->assertHasErrors('importFile');

    expect(Student::query()->count())->toBe(0);
});

test('XLSX import rejects a non-spreadsheet file', function () {
    $admin = studentImportAdmin();
    $file = UploadedFile::fake()->create('siswa.txt');

    Livewire::actingAs($admin)
        ->test('pages.students.import')
        ->set('importFile', $file)
        ->call('previewImport')
        ->assertHasErrors('importFile');
});

test('student XLSX template is available only to staff allowed to create student records', function () {
    $admin = studentImportAdmin();
    $this->actingAs($admin)
        ->get(route('students.import-template'))
        ->assertOk()
        ->assertDownload('template-impor-siswa.xlsx');
});
