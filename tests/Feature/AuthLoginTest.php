<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('user can sign in with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'operator@sekolah.local',
        'password' => 'password-panjang-aman',
    ]);

    Livewire::test('pages.auth.login')
        ->set('email', $user->email)
        ->set('password', 'password-panjang-aman')
        ->call('login')
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('login limits repeated invalid passwords', function () {
    User::factory()->create([
        'email' => 'operator@sekolah.local',
        'password' => 'password-panjang-aman',
    ]);

    foreach (range(1, 5) as $_) {
        Livewire::test('pages.auth.login')
            ->set('email', 'operator@sekolah.local')
            ->set('password', 'incorrect-password')
            ->call('login')
            ->assertHasErrors(['email']);
    }

    Livewire::test('pages.auth.login')
        ->set('email', 'operator@sekolah.local')
        ->set('password', 'password-panjang-aman')
        ->call('login')
        ->assertHasErrors(['email'])
        ->assertSee('Terlalu banyak percobaan');

    $this->assertGuest();
});
