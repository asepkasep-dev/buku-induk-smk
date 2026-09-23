<?php

use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('student can open own report score list without the add score button', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);

    $student = Student::create([
        'nis' => 'TEST-LIVEWIRE-001',
        'full_name' => 'Siswa Livewire Test',
        'gender' => 'L',
        'entry_year' => 2099,
        'status' => 'AKTIF',
    ]);

    $user = User::factory()->create([
        'role_id' => Role::where('code', 'STUDENT')->firstOrFail()->id,
        'student_id' => $student->id,
    ]);

    expect($user->hasPermission('VIEW_STUDENT'))->toBeTrue();
    expect($user->hasPermission('VIEW_REPORT_SCORE'))->toBeTrue();
    expect($user->hasPermission('INPUT_REPORT_SCORE'))->toBeFalse();

    $this->actingAs($user)
        ->get(route('report-scores.index', $student))
        ->assertOk()
        ->assertSeeLivewire('pages.report-scores.index');

    Livewire::actingAs($user)
        ->test('pages.report-scores.index', ['student' => $student])
        ->assertSee('Nilai Rapor')
        ->assertSee($student->full_name)
        ->assertSee('Belum ada nilai rapor untuk siswa ini.')
        ->assertDontSee('Tambah Nilai')
        ->assertDontSeeHtml('href="'.route('report-scores.create', $student).'"');
});

test('student receives 403 when opening another student report score list', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);

    $student = Student::create([
        'nis' => 'TEST-LIVEWIRE-001',
        'full_name' => 'Siswa Pemilik Akun',
        'gender' => 'L',
        'entry_year' => 2099,
        'status' => 'AKTIF',
    ]);

    $otherStudent = Student::create([
        'nis' => 'TEST-LIVEWIRE-002',
        'full_name' => 'Siswa Lain Rahasia',
        'gender' => 'P',
        'entry_year' => 2099,
        'status' => 'AKTIF',
    ]);

    $user = User::factory()->create([
        'role_id' => Role::where('code', 'STUDENT')->firstOrFail()->id,
        'student_id' => $student->id,
    ]);

    $this->actingAs($user)
        ->get(route('report-scores.index', $otherStudent))
        ->assertForbidden()
        ->assertDontSee($otherStudent->full_name);

    Livewire::actingAs($user)
        ->test('pages.report-scores.index', ['student' => $otherStudent])
        ->assertForbidden();
});
