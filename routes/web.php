<?php

use App\Http\Controllers\ReportCardController;
use App\Http\Controllers\StudentFileController;
use Illuminate\Support\Facades\Route;

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

Route::get('/students/import-template', [StudentFileController::class, 'downloadImportTemplate'])
    ->middleware('auth')
    ->name('students.import-template');

Route::livewire('/students/{student}/edit', 'pages.students.edit')
    ->middleware('auth')
    ->name('students.edit');

Route::livewire('/students/{student}/records', 'pages.students.records')
    ->middleware('auth')
    ->name('students.records');

Route::get('/students/{student}/documents/{document}/download', [StudentFileController::class, 'downloadDocument'])
    ->middleware('auth')
    ->name('students.documents.download');

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

// --- Ledger nilai e-rapor (Fase 1) ---
Route::livewire('/report-scores/{reportScore}/ledger', 'pages.report-scores.ledger')
    ->middleware('auth')
    ->name('report-scores.ledger');

// --- Rapor & Cetak (Fase 2) ---
Route::livewire('/students/{student}/report-cards/{semester}', 'pages.report-cards.show')
    ->middleware('auth')
    ->name('report-cards.show');

Route::get('/students/{student}/report-cards/{semester}/print', [ReportCardController::class, 'print'])
    ->middleware('auth')
    ->name('report-cards.print');

Route::get('/students/{student}/report-cards/{semester}/pdf', [ReportCardController::class, 'pdf'])
    ->middleware('auth')
    ->name('report-cards.pdf');

Route::livewire('/class/{rombel}/report-cards/{semester}', 'pages.report-cards.class-summary')
    ->middleware('auth')
    ->name('report-cards.class-summary');

Route::get('/class/{rombel}/report-cards/{semester}/batch-pdf', [ReportCardController::class, 'batchPdf'])
    ->middleware('auth')
    ->name('report-cards.batch-pdf');

// --- Kontrol Mutu & Finalisasi Massal (Fase 3) ---
Route::livewire('/admin/report-cards/overview', 'pages.admin.report-cards-overview')
    ->middleware('auth')
    ->name('admin.report-cards.overview');
