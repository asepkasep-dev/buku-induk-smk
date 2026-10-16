<?php

use App\Models\Student;
use App\Models\StudentDocument;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::livewire('/login', 'pages.auth.login')
    ->middleware(['guest', 'throttle:10,1'])
    ->name('login');

Route::livewire('/dashboard', 'pages.dashboard')
    ->middleware('auth')
    ->name('dashboard');

Route::livewire('/admin/academic-setup', 'pages.admin.academic-setup')
    ->middleware('auth')
    ->name('admin.academic-setup');

Route::livewire('/admin/users', 'pages.admin.users')
    ->middleware('auth')
    ->name('admin.users');

Route::livewire('/students', 'pages.students.index')
    ->middleware('auth')
    ->name('students.index');

Route::livewire('/students/create', 'pages.students.create')
    ->middleware('auth')
    ->name('students.create');

Route::livewire('/students/import', 'pages.students.import')
    ->middleware('auth')
    ->name('students.import');

Route::get('/students/import-template', function () {
    abort_unless(auth()->user()?->can('create', Student::class) && auth()->user()?->role?->code === 'ADMIN', 403);

    return response()->streamDownload(function (): void {
        $stream = fopen('php://output', 'wb');
        fputcsv($stream, ['nis', 'nisn', 'nik', 'full_name', 'gender', 'entry_year', 'status', 'phone', 'email', 'address']);
        fputcsv($stream, ['SMK-001', '', '', 'Nama Siswa', 'L', now()->year, 'CALON', '', '', '']);
        fclose($stream);
    }, 'template-impor-siswa.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
})->middleware('auth')->name('students.import-template');

Route::livewire('/students/{student}/edit', 'pages.students.edit')
    ->middleware('auth')
    ->name('students.edit');

Route::livewire('/students/{student}/records', 'pages.students.records')
    ->middleware('auth')
    ->name('students.records');

Route::get('/students/{student}/documents/{document}/download', function (Student $student, StudentDocument $document) {
    abort_unless(auth()->user()?->can('view', $student), 403);
    abort_unless(auth()->user()?->hasPermission('MANAGE_STUDENT_DOCUMENT'), 403);
    abort_unless((int) $document->student_id === (int) $student->id, 404);
    abort_unless(Storage::disk('local')->exists($document->file_path), 404);
    $name = basename(str_replace('\\', '/', $document->file_name));
    $name = preg_replace('/[\r\n"]/', '_', $name);

    return Storage::disk('local')->download($document->file_path, $name);
})->middleware('auth')->name('students.documents.download');

Route::livewire('/students/{student}', 'pages.students.show')
    ->middleware('auth')
    ->name('students.show');

Route::livewire(
    '/students/{student}/report-scores',
    'pages.report-scores.index'
)
    ->middleware('auth')
    ->name('report-scores.index');

Route::livewire(
    '/students/{student}/report-scores/create',
    'pages.report-scores.create'
)
    ->middleware('auth')
    ->name('report-scores.create');

Route::livewire('/report-scores/{reportScore}/edit', 'pages.report-scores.edit')
    ->middleware('auth')
    ->name('report-scores.edit');

// --- STUB Fase 1 (tampilan e-rapor; backend menyusul) ---
Route::livewire('/e-rapor/ledger', 'pages.report-scores.ledger')
    ->middleware('auth')
    ->name('erdapor.ledger');
