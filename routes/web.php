<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::livewire('/login', 'pages.auth.login')
    ->middleware('guest')
    ->name('login');


Route::livewire('/dashboard', 'pages.dashboard')
    ->middleware('auth')
    ->name('dashboard');

Route::livewire('/students', 'pages.students.index')
    ->middleware('auth')
    ->name('students.index');

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