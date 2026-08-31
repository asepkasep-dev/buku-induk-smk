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

Route::livewire('/report-scores/{reportScore}/edit', 'pages.report-scores.edit')
    ->middleware('auth')
    ->name('report-scores.edit');