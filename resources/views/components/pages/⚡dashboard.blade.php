<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public function logout()
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }
};
?>

<div>
    <h1>Dashboard Buku Induk Siswa</h1>

    <p>
        Selamat datang, {{ auth()->user()->name }}
    </p>

    <p>
        Role: {{ auth()->user()->role?->name ?? '-' }}
    </p>

    <button type="button" wire:click="logout">
        Logout
    </button>
</div>